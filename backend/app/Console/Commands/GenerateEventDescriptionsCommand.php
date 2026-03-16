<?php

namespace App\Console\Commands;

use App\Jobs\GenerateEventDescriptionJob;
use App\Models\DescriptionGenerationRun;
use App\Models\Event;
use App\Services\Events\DescriptionGenerationRunMetricsService;
use App\Services\Events\EventDescriptionGeneratorService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Throwable;

class GenerateEventDescriptionsCommand extends Command
{
    private const EXIT_FATAL = 1;
    private const EXIT_PARTIAL_FAILURE = 2;
    private const SAFE_CONCURRENCY_CAP = 3;
    private const DESCRIPTIONS_QUEUE = 'descriptions';
    private const MEMORY_PRESSURE_WARN_RATIO = 0.80;

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
                            {--fallback=skip : ollama failure strategy: base|skip}
                            {--concurrency= : Expected worker concurrency for queue jobs}
                            {--unsafe : Allow concurrency above the safe cap}';

    protected $description = 'Queue Slovak event description generation with robust resume/retry/fallback behavior.';

    public function __construct(
        private readonly EventDescriptionGeneratorService $generatorService,
        private readonly DescriptionGenerationRunMetricsService $metricsService,
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
            $unsafeConcurrency = (bool) $this->option('unsafe');

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

            $concurrencyWarning = null;
            $concurrency = $this->resolveConcurrency(
                rawValue: $this->option('concurrency'),
                unsafe: $unsafeConcurrency,
                warning: $concurrencyWarning
            );
            if ($concurrency === null) {
                $this->error('Invalid --concurrency value. Must be a positive integer.');
                return self::EXIT_FATAL;
            }

            if ($concurrencyWarning !== null) {
                $this->warn($concurrencyWarning);
            }

            if ($resumeEnabled && $ids === [] && $fromId === 0 && ! $dryRun) {
                $activeRun = $this->findActiveRun(
                    requestedMode: $requestedMode,
                    force: $force
                );

                if ($activeRun !== null) {
                    $target = (int) data_get($activeRun->meta, 'target_processed', 0);
                    $this->warn(sprintf(
                        'Run #%d is already in progress (%d/%d processed). Wait for workers to finish.',
                        (int) $activeRun->id,
                        (int) $activeRun->processed,
                        $target
                    ));
                    $this->line(sprintf(
                        'Worker command: php artisan queue:work --queue=%s --sleep=1 --tries=1',
                        self::DESCRIPTIONS_QUEUE
                    ));

                    return self::SUCCESS;
                }
            }

            $effectiveMode = $requestedMode;
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
                        $this->warn('Fallback=base enabled. Continuing in base mode for this run.');
                    } else {
                        $this->warn('Fallback=skip enabled. Run continues, but failed events will be skipped.');
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

            $eventIds = $baseQuery->pluck('id')->map(static fn ($id): int => (int) $id)->all();
            $total = count($eventIds);

            if ($total === 0) {
                $run->fill([
                    'status' => 'completed',
                    'finished_at' => now(),
                    'effective_mode' => $effectiveMode,
                    'fallback_mode' => $fallbackMode,
                ])->save();

                $this->info('No events require description generation.');
                $this->printMetrics($run->fresh());
                return self::SUCCESS;
            }

            $this->warnIfMemoryPressure($concurrency);

            $retryAttempts = $this->resolveRetryAttempts();
            $retryBackoff = $this->resolveRetryBackoffSeconds($retryAttempts);

            $lastDispatchedEventId = max($eventIds);
            $hasRemainingAfterBatch = $this->hasRemainingEvents(
                ids: $ids,
                force: $force,
                initialFromId: $effectiveFromId,
                lastEventId: $lastDispatchedEventId
            );

            $meta = (array) ($run->meta ?? []);
            unset(
                $meta['finalized_at'],
                $meta['dispatch_completed_at'],
                $meta['dispatch_duration_seconds'],
                $meta['last_error_code'],
                $meta['last_error_message']
            );

            $meta = array_merge($meta, [
                'queue' => self::DESCRIPTIONS_QUEUE,
                'concurrency' => $concurrency,
                'unsafe_concurrency' => $unsafeConcurrency,
                'retry_attempts' => $retryAttempts,
                'retry_backoff_seconds' => $retryBackoff,
                'queued_total' => $total,
                'target_processed' => (int) $run->processed + $total,
                'has_remaining_after_batch' => $hasRemainingAfterBatch,
                'dispatch_started_at' => now()->toIso8601String(),
            ]);

            $run->fill([
                'status' => 'running',
                'effective_mode' => $effectiveMode,
                'fallback_mode' => $fallbackMode,
                'resume_enabled' => $resumeEnabled,
                'force_enabled' => $force,
                'dry_run' => $dryRun,
                'from_id' => $effectiveFromId,
                'limit' => $limit > 0 ? $limit : null,
                'meta' => $meta,
                'error_message' => null,
            ])->save();

            config()->set('ai.ollama_runtime_concurrency', $concurrency);

            $dispatchStartedAt = microtime(true);
            foreach ($eventIds as $eventId) {
                GenerateEventDescriptionJob::dispatch(
                    runId: (int) $run->id,
                    eventId: (int) $eventId,
                    force: $force,
                    dryRun: $dryRun,
                    requestedMode: $requestedMode,
                    fallbackMode: $fallbackMode,
                    retryAttempts: $retryAttempts,
                    retryBackoffSeconds: $retryBackoff,
                    concurrency: $concurrency
                )->onQueue(self::DESCRIPTIONS_QUEUE);
            }

            $dispatchDurationSeconds = microtime(true) - $dispatchStartedAt;
            $this->markDispatchCompleted((int) $run->id, $dispatchDurationSeconds);

            $this->info(sprintf(
                'Dispatched %d events to queue "%s" (run_id=%d, mode=%s, fallback=%s, concurrency=%d).',
                $total,
                self::DESCRIPTIONS_QUEUE,
                (int) $run->id,
                $requestedMode,
                $fallbackMode,
                $concurrency
            ));
            $this->line('Windows worker command: php artisan queue:work --queue=descriptions --sleep=1 --tries=1');
            $this->line(sprintf('Run %d worker terminal(s) for concurrency=%d.', $concurrency, $concurrency));

            $run->refresh();
            if (in_array((string) $run->status, ['completed', 'completed_with_failures', 'partial'], true)) {
                $this->info(sprintf(
                    'Run summary run_id=%d processed=%d generated=%d failed=%d skipped=%d status=%s requested_mode=%s effective_mode=%s fallback=%s',
                    (int) $run->id,
                    (int) $run->processed,
                    (int) $run->generated,
                    (int) $run->failed,
                    (int) $run->skipped,
                    (string) $run->status,
                    $requestedMode,
                    (string) $run->effective_mode,
                    $fallbackMode
                ));
                $this->printMetrics($run);

                if ((string) $run->status === 'completed_with_failures') {
                    return self::EXIT_PARTIAL_FAILURE;
                }
            } else {
                $this->line(sprintf(
                    'Run #%d is processing asynchronously (%d/%d processed).',
                    (int) $run->id,
                    (int) $run->processed,
                    (int) data_get($run->meta, 'target_processed', (int) $run->processed)
                ));
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

    private function findActiveRun(string $requestedMode, bool $force): ?DescriptionGenerationRun
    {
        $run = DescriptionGenerationRun::query()
            ->where('status', 'running')
            ->where('dry_run', false)
            ->where('force_enabled', $force)
            ->where('requested_mode', $requestedMode)
            ->latest('id')
            ->first();

        if ($run === null) {
            return null;
        }

        $targetProcessed = (int) data_get($run->meta, 'target_processed', 0);
        if ($targetProcessed <= 0) {
            return null;
        }

        return (int) $run->processed < $targetProcessed ? $run : null;
    }

    private function markDispatchCompleted(int $runId, float $dispatchDurationSeconds): void
    {
        $run = DescriptionGenerationRun::query()->find($runId);
        if ($run === null) {
            return;
        }

        $meta = array_merge((array) ($run->meta ?? []), [
            'dispatch_completed_at' => now()->toIso8601String(),
            'dispatch_duration_seconds' => round($dispatchDurationSeconds, 3),
        ]);

        $run->fill(['meta' => $meta])->save();
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

    private function resolveRetryAttempts(): int
    {
        // Keep CLI queue runs deterministic: one app-level attempt per event.
        return 1;
    }

    /**
     * @return array<int,int>
     */
    private function resolveRetryBackoffSeconds(int $retryAttempts): array
    {
        return array_fill(0, max(1, $retryAttempts), 0);
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

    private function resolveConcurrency(mixed $rawValue, bool $unsafe, ?string &$warning): ?int
    {
        $warning = null;

        $configuredDefault = max(1, (int) config('ai.ollama_safe_concurrency_default', 2));
        $value = trim((string) ($rawValue ?? ''));
        $concurrency = $value === '' ? $configuredDefault : (int) $value;

        if ($concurrency <= 0) {
            return null;
        }

        if (! $unsafe && $concurrency > self::SAFE_CONCURRENCY_CAP) {
            $warning = sprintf(
                'Requested concurrency=%d exceeds safe cap=%d. Capped to %d. Use --unsafe to override.',
                $concurrency,
                self::SAFE_CONCURRENCY_CAP,
                self::SAFE_CONCURRENCY_CAP
            );
            $concurrency = self::SAFE_CONCURRENCY_CAP;
        }

        return $concurrency;
    }

    private function warnIfMemoryPressure(int $concurrency): void
    {
        $processUsageBytes = memory_get_usage(true);
        $memoryLimitBytes = $this->parseIniBytes((string) ini_get('memory_limit'));

        if ($memoryLimitBytes !== null && $memoryLimitBytes > 0) {
            $ratio = $processUsageBytes / $memoryLimitBytes;
            if ($ratio >= self::MEMORY_PRESSURE_WARN_RATIO) {
                $this->warn(sprintf(
                    'PHP process memory usage is high (%.1f%% of memory_limit). Consider reducing --concurrency.',
                    $ratio * 100
                ));
            }
        }

        $systemUsage = $this->detectWindowsSystemMemoryUsagePercent();
        if ($systemUsage !== null && $systemUsage >= 80.0) {
            $this->warn(sprintf(
                'System RAM usage is %.1f%%. Reduce --concurrency (current=%d) to avoid Ollama overload.',
                $systemUsage,
                $concurrency
            ));
        }
    }

    private function parseIniBytes(string $raw): ?int
    {
        $value = strtolower(trim($raw));
        if ($value === '' || $value === '-1') {
            return null;
        }

        $unit = substr($value, -1);
        $number = is_numeric($unit) ? (float) $value : (float) substr($value, 0, -1);
        if (! is_finite($number) || $number <= 0) {
            return null;
        }

        return match ($unit) {
            'g' => (int) round($number * 1024 * 1024 * 1024),
            'm' => (int) round($number * 1024 * 1024),
            'k' => (int) round($number * 1024),
            default => (int) round($number),
        };
    }

    private function detectWindowsSystemMemoryUsagePercent(): ?float
    {
        if (PHP_OS_FAMILY !== 'Windows' || ! function_exists('shell_exec')) {
            return null;
        }

        $wmicOutput = @shell_exec('wmic OS get FreePhysicalMemory,TotalVisibleMemorySize /Value 2>NUL');
        if (is_string($wmicOutput) && trim($wmicOutput) !== '') {
            $total = null;
            $free = null;

            foreach (preg_split('/\r\n|\r|\n/', trim($wmicOutput)) as $line) {
                if (! is_string($line) || ! str_contains($line, '=')) {
                    continue;
                }

                [$key, $value] = array_map('trim', explode('=', $line, 2));
                if ($key === 'TotalVisibleMemorySize') {
                    $total = (float) $value;
                }
                if ($key === 'FreePhysicalMemory') {
                    $free = (float) $value;
                }
            }

            if ($total !== null && $total > 0 && $free !== null && $free >= 0) {
                return round((($total - $free) / $total) * 100, 2);
            }
        }

        $powerShellOutput = @shell_exec('powershell -NoProfile -Command "$os = Get-CimInstance Win32_OperatingSystem; if ($os.TotalVisibleMemorySize -gt 0) { [math]::Round((($os.TotalVisibleMemorySize - $os.FreePhysicalMemory) / $os.TotalVisibleMemorySize) * 100, 2) }"');
        if (! is_string($powerShellOutput)) {
            return null;
        }

        $value = trim($powerShellOutput);
        if ($value === '' || ! is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }

    private function printMetrics(?DescriptionGenerationRun $run): void
    {
        if (! $run instanceof DescriptionGenerationRun) {
            return;
        }

        $metrics = $this->metricsService->summarize($run);
        $this->line(sprintf('Total duration: %.2fs', (float) $metrics['duration_seconds']));
        $this->line(sprintf('Average seconds/event: %.2f', (float) $metrics['average_seconds_per_event']));
        $this->line(sprintf('Estimated throughput: %.2f events/minute', (float) $metrics['throughput_events_per_minute']));
        $this->line(sprintf('Suggested concurrency: %d', (int) $metrics['suggested_concurrency']));
    }
}
