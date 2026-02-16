<?php

namespace App\Console\Commands;

use App\Models\DescriptionGenerationRun;
use App\Models\Event;
use App\Services\AI\OllamaClientException;
use App\Services\Events\EventDescriptionGeneratorService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Throwable;

class GenerateEventDescriptionsCommand extends Command
{
    private const EXIT_FATAL = 1;
    private const EXIT_PARTIAL_FAILURE = 2;

    protected $signature = 'events:generate-descriptions
                            {--limit=0 : Max events to process (0 = all)}
                            {--dry-run : Do not persist changes}
                            {--force : Regenerate even when description already exists}
                            {--ids= : Comma-separated event IDs}
                            {--mode= : template|ollama (default from config)}
                            {--ollama : Shortcut for --mode=ollama}
                            {--resume : Enable automatic resume from unfinished run}
                            {--no-resume : Disable automatic resume}
                            {--from-id= : Start from event ID (inclusive)}
                            {--fallback=skip : ollama failure strategy: base|skip}';

    protected $description = 'Generate Slovak event descriptions with robust resume/retry/fallback behavior. Exit codes: 0 success, 2 completed_with_failures, 1 fatal.';

    public function __construct(
        private readonly EventDescriptionGeneratorService $generatorService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            $limit = max(0, (int) $this->option('limit'));
            $dryRun = (bool) $this->option('dry-run');
            $force = (bool) $this->option('force');
            $ids = $this->parseIds((string) $this->option('ids'));

            $requestedMode = $this->resolveMode(
                rawMode: (string) $this->option('mode'),
                ollamaFlag: (bool) $this->option('ollama')
            );
            if ($requestedMode === null) {
                $this->error('Invalid mode. Allowed: template, ollama.');
                return self::EXIT_FATAL;
            }

            $fallbackMode = $this->resolveFallbackMode((string) $this->option('fallback'));
            if ($fallbackMode === null) {
                $this->error('Invalid fallback mode. Allowed: base, skip.');
                return self::EXIT_FATAL;
            }

            $fromId = $this->parseFromId($this->option('from-id'));
            if ($fromId === -1) {
                $this->error('Invalid --from-id value. Must be a positive integer.');
                return self::EXIT_FATAL;
            }

            $resumeEnabled = $this->resolveResumeEnabled(
                resumeFlag: (bool) $this->option('resume'),
                noResumeFlag: (bool) $this->option('no-resume'),
                ids: $ids
            );
            if ($resumeEnabled === null) {
                $this->error('Cannot use --resume and --no-resume together.');
                return self::EXIT_FATAL;
            }

            $effectiveMode = $requestedMode;
            $usingFallbackBaseForRemaining = false;

            if ($requestedMode === 'ollama') {
                $health = $this->generatorService->preflightOllama();
                $this->line(sprintf(
                    'Ollama endpoint: %s | model: %s',
                    $health['endpoint'],
                    $health['model']
                ));

                if (! $health['ok']) {
                    $this->warn('Ollama pre-flight failed: ' . (string) ($health['message'] ?? 'unknown error'));
                    if ($fallbackMode === 'base') {
                        $effectiveMode = 'template';
                        $usingFallbackBaseForRemaining = true;
                        $this->warn('Fallback=base enabled. Continuing in base mode for this run.');
                    } else {
                        $this->warn('Fallback=skip enabled. Run continues, but some events may fail.');
                    }
                } else {
                    $this->info('Ollama pre-flight health check: OK');
                }
            }

            [$run, $effectiveFromId, $resumedRun] = $this->resolveRun(
                requestedMode: $requestedMode,
                effectiveMode: $effectiveMode,
                fallbackMode: $fallbackMode,
                resumeEnabled: $resumeEnabled,
                force: $force,
                dryRun: $dryRun,
                fromId: $fromId > 0 ? $fromId : null,
                limit: $limit,
                ids: $ids
            );

            if ($resumedRun) {
                $this->info(sprintf(
                    'Resuming run #%d from event ID > %d.',
                    (int) $run->id,
                    (int) ($run->last_event_id ?? 0)
                ));
            } else {
                $this->info(sprintf('Started run #%d.', (int) $run->id));
            }

            $baseQuery = $this->buildEventQuery(
                ids: $ids,
                fromId: $effectiveFromId,
                force: $force
            );

            if ($limit > 0) {
                $baseQuery->limit($limit);
            }

            $events = $baseQuery->get();
            $total = $events->count();

            if ($total === 0) {
                $run->fill([
                    'status' => 'completed',
                    'finished_at' => now(),
                    'effective_mode' => $effectiveMode,
                    'fallback_mode' => $fallbackMode,
                ])->save();

                $this->info('No events require description generation.');
                return self::SUCCESS;
            }

            $retryAttempts = $this->resolveRetryAttempts();
            $retryBackoff = $this->resolveRetryBackoffSeconds($retryAttempts);

            $processed = (int) $run->processed;
            $generated = (int) $run->generated;
            $failed = (int) $run->failed;
            $skipped = (int) $run->skipped;

            $this->info(sprintf(
                'Processing %d events (requested_mode=%s, effective_mode=%s, fallback=%s, retries=%d, dry_run=%s).',
                $total,
                $requestedMode,
                $effectiveMode,
                $fallbackMode,
                $retryAttempts,
                $dryRun ? 'yes' : 'no'
            ));
            $this->line('Exit codes: 0=success, 2=completed_with_failures, 1=fatal_config_error.');

            $this->output->progressStart($total);

            foreach ($events as $event) {
                $attemptsUsed = 0;
                $eventStatus = 'generated';
                $errorCode = null;
                $errorMessage = null;

                try {
                    $generationResult = null;
                    $shouldSwitchToBase = false;

                    if ($effectiveMode === 'template') {
                        $attemptsUsed = 1;
                        $generationResult = $this->generatorService->generateForEvent($event, 'template');
                    } else {
                        $attemptsUsed = 0;
                        $generationResult = $this->generateWithRetry(
                            event: $event,
                            retryAttempts: $retryAttempts,
                            retryBackoffSeconds: $retryBackoff,
                            attemptsUsed: $attemptsUsed,
                            errorCode: $errorCode,
                            errorMessage: $errorMessage
                        );

                        if ($generationResult === null && $fallbackMode === 'base') {
                            $shouldSwitchToBase = true;
                            $usingFallbackBaseForRemaining = true;
                            $generationResult = $this->generatorService->generateForEvent($event, 'template');
                        }
                    }

                    if ($generationResult === null) {
                        $failed++;
                        if ($fallbackMode === 'skip') {
                            $skipped++;
                            $eventStatus = 'skipped';
                        } else {
                            $eventStatus = 'failed';
                        }
                    } else {
                        if (! $dryRun) {
                            $event->update([
                                'description' => (string) $generationResult['description'],
                                'short' => (string) $generationResult['short'],
                            ]);
                        }

                        $generated++;
                        $eventStatus = $shouldSwitchToBase
                            ? 'generated_base_fallback'
                            : (string) ($generationResult['provider'] ?? 'generated');

                        if ($shouldSwitchToBase && $effectiveMode !== 'template') {
                            $effectiveMode = 'template';
                            $this->warn('Switching remaining events to base mode due to Ollama outage.');
                        }
                    }
                } catch (Throwable $exception) {
                    $attemptsUsed = max($attemptsUsed, 1);
                    $failed++;
                    $errorCode = $this->resolveErrorCode($exception);
                    $errorMessage = $exception->getMessage();
                    $eventStatus = 'failed';
                }

                $processed++;

                $run->fill([
                    'effective_mode' => $effectiveMode,
                    'last_event_id' => (int) $event->id,
                    'processed' => $processed,
                    'generated' => $generated,
                    'failed' => $failed,
                    'skipped' => $skipped,
                    'meta' => array_filter([
                        'using_fallback_base_for_remaining' => $usingFallbackBaseForRemaining,
                        'last_event_status' => $eventStatus,
                        'last_error_code' => $errorCode,
                        'last_error_message' => $errorMessage,
                        'last_attempts' => $attemptsUsed,
                    ], static fn ($value): bool => $value !== null),
                ])->save();

                $this->output->progressAdvance();
                $this->line(sprintf(
                    ' event_id=%d status=%s attempts=%d processed=%d generated=%d failed=%d skipped=%d',
                    (int) $event->id,
                    $eventStatus,
                    $attemptsUsed,
                    $processed,
                    $generated,
                    $failed,
                    $skipped
                ));
            }

            $this->output->progressFinish();
            $this->newLine();

            $hasRemaining = $this->hasRemainingEvents(
                ids: $ids,
                force: $force,
                initialFromId: $effectiveFromId,
                lastEventId: (int) ($run->last_event_id ?? 0)
            );

            if (! $dryRun && $hasRemaining) {
                $run->fill([
                    'status' => 'partial',
                    'finished_at' => null,
                    'effective_mode' => $effectiveMode,
                    'error_message' => null,
                ])->save();
            } else {
                $run->fill([
                    'status' => $failed > 0 ? 'completed_with_failures' : 'completed',
                    'finished_at' => now(),
                    'effective_mode' => $effectiveMode,
                    'error_message' => null,
                ])->save();
            }

            $this->info(sprintf(
                'Run summary run_id=%d processed=%d generated=%d failed=%d skipped=%d status=%s requested_mode=%s effective_mode=%s fallback=%s',
                (int) $run->id,
                $processed,
                $generated,
                $failed,
                $skipped,
                (string) $run->status,
                $requestedMode,
                $effectiveMode,
                $fallbackMode
            ));

            if ($failed > 0) {
                $this->warn('Completed with failures. Exit code: 2');
                return self::EXIT_PARTIAL_FAILURE;
            }

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error('Fatal error: ' . $exception->getMessage());
            return self::EXIT_FATAL;
        }
    }

    /**
     * @return array{0:DescriptionGenerationRun,1:int|null,2:bool}
     */
    private function resolveRun(
        string $requestedMode,
        string $effectiveMode,
        string $fallbackMode,
        bool $resumeEnabled,
        bool $force,
        bool $dryRun,
        ?int $fromId,
        int $limit,
        array $ids
    ): array {
        if ($resumeEnabled && $ids === [] && $fromId === null && ! $dryRun) {
            $running = DescriptionGenerationRun::query()
                ->whereIn('status', ['running', 'partial'])
                ->where('dry_run', false)
                ->where('force_enabled', $force)
                ->where('requested_mode', $requestedMode)
                ->latest('id')
                ->first();

            if ($running !== null) {
                $resumeFrom = max(1, (int) ($running->last_event_id ?? 0) + 1);
                $running->fill([
                    'status' => 'running',
                    'effective_mode' => $effectiveMode,
                    'fallback_mode' => $fallbackMode,
                    'resume_enabled' => true,
                    'limit' => $limit > 0 ? $limit : null,
                    'from_id' => $resumeFrom,
                    'meta' => array_merge((array) ($running->meta ?? []), [
                        'resumed_at' => now()->toIso8601String(),
                    ]),
                ])->save();

                return [$running, $resumeFrom, true];
            }
        }

        $run = DescriptionGenerationRun::query()->create([
            'started_at' => now(),
            'status' => 'running',
            'requested_mode' => $requestedMode,
            'effective_mode' => $effectiveMode,
            'fallback_mode' => $fallbackMode,
            'resume_enabled' => $resumeEnabled,
            'force_enabled' => $force,
            'dry_run' => $dryRun,
            'from_id' => $fromId,
            'limit' => $limit > 0 ? $limit : null,
            'processed' => 0,
            'generated' => 0,
            'failed' => 0,
            'skipped' => 0,
            'meta' => [
                'ids_count' => count($ids),
                'ids_sample' => array_slice($ids, 0, 20),
            ],
        ]);

        return [$run, $fromId, false];
    }

    private function hasRemainingEvents(array $ids, bool $force, ?int $initialFromId, int $lastEventId): bool
    {
        if ($lastEventId <= 0) {
            return false;
        }

        $query = $this->buildEventQuery(
            ids: $ids,
            fromId: $initialFromId,
            force: $force
        )->where('id', '>', $lastEventId);

        return $query->exists();
    }

    private function buildEventQuery(array $ids, ?int $fromId, bool $force): Builder
    {
        $query = Event::query()->orderBy('id');

        if ($ids !== []) {
            $query->whereIn('id', $ids);
        } elseif ($fromId !== null && $fromId > 0) {
            $query->where('id', '>=', $fromId);
        }

        if (! $force) {
            $query->where(function (Builder $builder): void {
                $builder->whereNull('description')
                    ->orWhere('description', '')
                    ->orWhereNull('short')
                    ->orWhere('short', '');
            });
        }

        return $query;
    }

    /**
     * @return array{description:string,short:string,provider:string}|null
     */
    private function generateWithRetry(
        Event $event,
        int $retryAttempts,
        array $retryBackoffSeconds,
        int &$attemptsUsed,
        ?string &$errorCode,
        ?string &$errorMessage
    ): ?array {
        for ($attempt = 1; $attempt <= $retryAttempts; $attempt++) {
            $attemptsUsed = $attempt;

            try {
                return $this->generatorService->generateForEvent($event, 'ollama');
            } catch (Throwable $exception) {
                $errorCode = $this->resolveErrorCode($exception);
                $errorMessage = $exception->getMessage();

                $retryable = $this->isRetryableOllamaException($exception);
                if (! $retryable) {
                    return null;
                }

                if ($attempt >= $retryAttempts) {
                    return null;
                }

                $backoff = (int) ($retryBackoffSeconds[$attempt - 1] ?? 0);
                if ($backoff > 0) {
                    usleep($backoff * 1_000_000);
                }
            }
        }

        return null;
    }

    private function resolveErrorCode(Throwable $exception): string
    {
        foreach ($this->exceptionChain($exception) as $item) {
            if ($item instanceof OllamaClientException) {
                return $item->errorCode();
            }
        }

        $message = strtolower($exception->getMessage());
        if (str_contains($message, 'timeout')) {
            return 'ollama_timeout';
        }
        if (str_contains($message, 'connection')) {
            return 'ollama_connection_error';
        }

        return 'description_generation_error';
    }

    private function isRetryableOllamaException(Throwable $exception): bool
    {
        foreach ($this->exceptionChain($exception) as $item) {
            if ($item instanceof OllamaClientException) {
                $errorCode = $item->errorCode();

                if (in_array($errorCode, ['ollama_connection_error', 'ollama_service_error'], true)) {
                    return true;
                }

                if (str_starts_with($errorCode, 'ollama_http_')) {
                    $status = (int) substr($errorCode, strlen('ollama_http_'));
                    return $status >= 500 || $status === 429;
                }

                return false;
            }

            $message = strtolower($item->getMessage());
            if (
                str_contains($message, 'connection failed')
                || str_contains($message, 'timed out')
                || str_contains($message, 'timeout')
                || str_contains($message, 'could not connect')
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int,Throwable>
     */
    private function exceptionChain(Throwable $exception): array
    {
        $chain = [];
        $current = $exception;

        while ($current !== null) {
            $chain[] = $current;
            $current = $current->getPrevious();
        }

        return $chain;
    }

    private function resolveRetryAttempts(): int
    {
        return max(1, (int) config('ai.ollama_retry_attempts', 3));
    }

    /**
     * @return array<int,int>
     */
    private function resolveRetryBackoffSeconds(int $retryAttempts): array
    {
        $configured = config('ai.ollama_retry_backoff_seconds', [1, 3, 7]);
        if (! is_array($configured)) {
            $configured = [1, 3, 7];
        }

        $backoff = array_values(array_map(
            static fn ($value): int => max(0, (int) $value),
            $configured
        ));

        if ($backoff === []) {
            $backoff = [1, 3, 7];
        }

        while (count($backoff) < $retryAttempts) {
            $backoff[] = (int) end($backoff);
        }

        return array_slice($backoff, 0, $retryAttempts);
    }

    /**
     * @return array<int,int>
     */
    private function parseIds(string $raw): array
    {
        $value = trim($raw);
        if ($value === '') {
            return [];
        }

        $parts = array_map(
            static fn (string $item): int => (int) trim($item),
            explode(',', $value)
        );

        $parts = array_values(array_filter($parts, static fn (int $id): bool => $id > 0));
        return array_values(array_unique($parts));
    }

    private function resolveMode(string $rawMode, bool $ollamaFlag): ?string
    {
        if ($ollamaFlag) {
            return 'ollama';
        }

        $value = strtolower(trim($rawMode));
        if ($value === '') {
            $value = strtolower(trim((string) config('events.ai.description_mode', 'template')));
        }

        return in_array($value, ['template', 'ollama'], true) ? $value : null;
    }

    private function resolveFallbackMode(string $raw): ?string
    {
        $value = strtolower(trim($raw));
        if ($value === '') {
            return 'skip';
        }

        return in_array($value, ['base', 'skip'], true) ? $value : null;
    }

    private function parseFromId(mixed $raw): int
    {
        if ($raw === null || trim((string) $raw) === '') {
            return 0;
        }

        $value = (int) $raw;
        return $value > 0 ? $value : -1;
    }

    private function resolveResumeEnabled(bool $resumeFlag, bool $noResumeFlag, array $ids): ?bool
    {
        if ($resumeFlag && $noResumeFlag) {
            return null;
        }

        if ($resumeFlag) {
            return true;
        }

        if ($noResumeFlag) {
            return false;
        }

        return $ids === [];
    }
}
