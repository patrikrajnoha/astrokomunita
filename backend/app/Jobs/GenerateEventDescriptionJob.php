<?php

namespace App\Jobs;

use App\Models\DescriptionGenerationRun;
use App\Models\Event;
use App\Services\AI\OllamaClientException;
use App\Services\Admin\AiLastRunStore;
use App\Services\Events\DescriptionGenerationRunMetricsService;
use App\Services\Events\EventDescriptionGeneratorService;
use App\Services\Events\EventInsightsCacheService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class GenerateEventDescriptionJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    /**
     * @param array<int,int> $retryBackoffSeconds
     */
    public function __construct(
        public readonly int $runId,
        public readonly int $eventId,
        public readonly bool $force,
        public readonly bool $dryRun,
        public readonly string $requestedMode,
        public readonly string $fallbackMode,
        public readonly int $retryAttempts,
        public readonly array $retryBackoffSeconds,
        public readonly int $concurrency,
    ) {
    }

    public function handle(
        EventDescriptionGeneratorService $generatorService,
        DescriptionGenerationRunMetricsService $metricsService,
        EventInsightsCacheService $insightsCache,
        AiLastRunStore $lastRunStore
    ): void {
        $jobStartedAt = microtime(true);

        config()->set('ai.ollama_runtime_concurrency', max(1, $this->concurrency));

        $run = DescriptionGenerationRun::query()->find($this->runId);
        if (! $run) {
            return;
        }

        $event = Event::query()->find($this->eventId);
        if (! $event) {
            $this->recordRunProgress(
                generatedDelta: 0,
                failedDelta: 1,
                skippedDelta: 0,
                eventStatus: 'missing_event',
                attemptsUsed: 0,
                errorCode: 'event_not_found',
                errorMessage: sprintf('Event with id=%d was not found.', $this->eventId),
                switchRemainingToTemplate: false,
                metricsService: $metricsService
            );

            $this->recordLastRunTelemetry(
                lastRunStore: $lastRunStore,
                eventId: $this->eventId,
                eventStatus: 'missing_event',
                failedDelta: 1,
                startedAt: $jobStartedAt,
                retryCount: 0
            );

            return;
        }

        if (! $this->force && $this->hasExistingDescription($event)) {
            $this->recordRunProgress(
                generatedDelta: 0,
                failedDelta: 0,
                skippedDelta: 1,
                eventStatus: 'skipped_existing',
                attemptsUsed: 0,
                errorCode: null,
                errorMessage: null,
                switchRemainingToTemplate: false,
                metricsService: $metricsService
            );

            $this->recordLastRunTelemetry(
                lastRunStore: $lastRunStore,
                eventId: (int) $event->id,
                eventStatus: 'skipped_existing',
                failedDelta: 0,
                startedAt: $jobStartedAt,
                retryCount: 0
            );

            return;
        }

        $attemptsUsed = 0;
        $eventStatus = 'generated';
        $errorCode = null;
        $errorMessage = null;
        $generatedDelta = 0;
        $failedDelta = 0;
        $skippedDelta = 0;
        $switchRemainingToTemplate = false;

        try {
            $generationResult = null;
            $effectiveMode = $this->resolveEffectiveMode();

            if ($effectiveMode === 'template') {
                $attemptsUsed = 1;
                $generationResult = $generatorService->generateForEvent($event, 'template');
            } else {
                $generationResult = $this->generateWithRetry(
                    event: $event,
                    generatorService: $generatorService,
                    attemptsUsed: $attemptsUsed,
                    errorCode: $errorCode,
                    errorMessage: $errorMessage
                );

                if ($generationResult === null && $this->fallbackMode === 'base') {
                    $switchRemainingToTemplate = true;
                    $generationResult = $generatorService->generateForEvent($event, 'template');
                }
            }

            if ($generationResult === null) {
                $failedDelta = 1;
                if ($this->fallbackMode === 'skip') {
                    $skippedDelta = 1;
                    $eventStatus = 'skipped';
                } else {
                    $eventStatus = 'failed';
                }
            } else {
                if (! $this->dryRun) {
                    // TODO(newsletter/admin-preview): consume optional "insights"
                    // from generator result once we introduce storage/rendering path
                    // for why_interesting/how_to_observe.
                    $this->storeInsightsForNewsletterPreview($event, $generationResult, $insightsCache);
                    $this->persistDescription(
                        $event,
                        (string) ($generationResult['description'] ?? ''),
                        (string) ($generationResult['short'] ?? '')
                    );
                }

                $generatedDelta = 1;
                $eventStatus = $switchRemainingToTemplate
                    ? 'generated_base_fallback'
                    : (string) ($generationResult['provider'] ?? 'generated');
            }
        } catch (Throwable $exception) {
            $failedDelta = 1;
            $eventStatus = 'failed';
            $attemptsUsed = max(1, $attemptsUsed);
            $errorCode = $this->resolveErrorCode($exception);
            $errorMessage = $exception->getMessage();
        }

        $this->recordRunProgress(
            generatedDelta: $generatedDelta,
            failedDelta: $failedDelta,
            skippedDelta: $skippedDelta,
            eventStatus: $eventStatus,
            attemptsUsed: $attemptsUsed,
            errorCode: $errorCode,
            errorMessage: $errorMessage,
            switchRemainingToTemplate: $switchRemainingToTemplate,
            metricsService: $metricsService
        );

        $this->recordLastRunTelemetry(
            lastRunStore: $lastRunStore,
            eventId: (int) $event->id,
            eventStatus: $eventStatus,
            failedDelta: $failedDelta,
            startedAt: $jobStartedAt,
            retryCount: max(0, $attemptsUsed - 1)
        );
    }

    private function hasExistingDescription(Event $event): bool
    {
        return filled($event->description) && filled($event->short);
    }

    private function resolveEffectiveMode(): string
    {
        $value = trim((string) DescriptionGenerationRun::query()
            ->whereKey($this->runId)
            ->value('effective_mode'));

        $value = strtolower($value);
        if ($value === '') {
            $value = strtolower(trim($this->requestedMode));
        }

        return in_array($value, ['template', 'ollama'], true) ? $value : 'template';
    }

    /**
     * @return array{description:string,short:string,provider:string}|null
     */
    private function generateWithRetry(
        Event $event,
        EventDescriptionGeneratorService $generatorService,
        int &$attemptsUsed,
        ?string &$errorCode,
        ?string &$errorMessage
    ): ?array {
        for ($attempt = 1; $attempt <= $this->retryAttempts; $attempt++) {
            $attemptsUsed = $attempt;

            try {
                return $generatorService->generateForEvent($event, 'ollama');
            } catch (Throwable $exception) {
                $errorCode = $this->resolveErrorCode($exception);
                $errorMessage = $exception->getMessage();

                if (! $this->isRetryableOllamaException($exception) || $attempt >= $this->retryAttempts) {
                    return null;
                }

                $backoffSeconds = (int) ($this->retryBackoffSeconds[$attempt - 1] ?? 0);
                if ($backoffSeconds > 0) {
                    usleep($backoffSeconds * 1_000_000);
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
                || str_contains($message, 'overload')
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

    private function recordRunProgress(
        int $generatedDelta,
        int $failedDelta,
        int $skippedDelta,
        string $eventStatus,
        int $attemptsUsed,
        ?string $errorCode,
        ?string $errorMessage,
        bool $switchRemainingToTemplate,
        DescriptionGenerationRunMetricsService $metricsService
    ): void {
        $completedRun = null;

        DB::transaction(function () use (
            $generatedDelta,
            $failedDelta,
            $skippedDelta,
            $eventStatus,
            $attemptsUsed,
            $errorCode,
            $errorMessage,
            $switchRemainingToTemplate,
            &$completedRun
        ): void {
            $run = DescriptionGenerationRun::query()->lockForUpdate()->find($this->runId);
            if (! $run) {
                return;
            }

            $meta = (array) ($run->meta ?? []);
            $processed = (int) $run->processed + 1;
            $generated = (int) $run->generated + max(0, $generatedDelta);
            $failed = (int) $run->failed + max(0, $failedDelta);
            $skipped = (int) $run->skipped + max(0, $skippedDelta);

            $meta['last_event_status'] = $eventStatus;
            $meta['last_attempts'] = $attemptsUsed;
            $meta['last_processed_event_id'] = $this->eventId;
            $meta['last_error_code'] = $errorCode;
            $meta['last_error_message'] = $errorMessage;

            if ($switchRemainingToTemplate) {
                $meta['using_fallback_base_for_remaining'] = true;
                $run->effective_mode = 'template';
            }

            $targetProcessed = max(0, (int) ($meta['target_processed'] ?? 0));
            $alreadyFinalized = isset($meta['finalized_at']);
            $hasReachedTarget = $targetProcessed > 0 && $processed >= $targetProcessed;

            $status = (string) $run->status;
            $finishedAt = $run->finished_at;

            if (! $alreadyFinalized && $hasReachedTarget) {
                $hasRemainingAfterBatch = ! $this->dryRun && (bool) ($meta['has_remaining_after_batch'] ?? false);

                if ($hasRemainingAfterBatch) {
                    $status = 'partial';
                    $finishedAt = null;
                } else {
                    $status = $failed > 0 ? 'completed_with_failures' : 'completed';
                    $finishedAt = now();
                }

                $meta['finalized_at'] = now()->toIso8601String();
                $meta['target_processed'] = $processed;
            } elseif (! $alreadyFinalized) {
                $status = 'running';
                $finishedAt = null;
            }

            $run->fill([
                'status' => $status,
                'finished_at' => $finishedAt,
                'last_event_id' => max((int) ($run->last_event_id ?? 0), $this->eventId),
                'processed' => $processed,
                'generated' => $generated,
                'failed' => $failed,
                'skipped' => $skipped,
                'meta' => $this->pruneMeta($meta),
                'error_message' => $failedDelta > 0 ? $errorMessage : $run->error_message,
            ])->save();

            if (! $alreadyFinalized && $hasReachedTarget) {
                $completedRun = $run->fresh();
            }
        });

        if ($completedRun instanceof DescriptionGenerationRun) {
            $this->emitMetrics($completedRun, $metricsService);
        }
    }

    /**
     * @param array<string,mixed> $meta
     * @return array<string,mixed>
     */
    private function pruneMeta(array $meta): array
    {
        return array_filter($meta, static fn ($value): bool => $value !== null);
    }

    private function persistDescription(Event $event, string $description, string $short): void
    {
        $normalizedDescription = trim($description);
        $normalizedShort = trim($short);

        $currentDescription = trim((string) ($event->description ?? ''));
        $currentShort = trim((string) ($event->short ?? ''));

        if ($normalizedDescription === $currentDescription && $normalizedShort === $currentShort) {
            return;
        }

        $event->update([
            'description' => $normalizedDescription,
            'short' => $normalizedShort,
        ]);
    }

    /**
     * @param array<string,mixed> $generationResult
     */
    private function storeInsightsForNewsletterPreview(
        Event $event,
        array $generationResult,
        EventInsightsCacheService $insightsCache
    ): void {
        if (! (bool) config('events.ai.humanized_pilot_enabled', false)) {
            return;
        }

        $insights = is_array($generationResult['insights'] ?? null)
            ? (array) $generationResult['insights']
            : [];

        $whyInteresting = trim((string) ($insights['why_interesting'] ?? ''));
        $howToObserve = trim((string) ($insights['how_to_observe'] ?? ''));
        if ($whyInteresting === '' && $howToObserve === '') {
            return;
        }

        $insightsCache->put(
            event: $event,
            whyInteresting: $whyInteresting,
            howToObserve: $howToObserve
        );
    }

    private function recordLastRunTelemetry(
        AiLastRunStore $lastRunStore,
        int $eventId,
        string $eventStatus,
        int $failedDelta,
        float $startedAt,
        int $retryCount
    ): void {
        $status = 'success';
        if ($failedDelta > 0 || in_array($eventStatus, ['failed', 'missing_event'], true)) {
            $status = 'error';
        } elseif (
            str_contains($eventStatus, 'fallback')
            || in_array($eventStatus, ['template', 'skipped_existing', 'skipped'], true)
        ) {
            $status = 'fallback';
        }

        $lastRunStore->put(
            featureName: 'event_description_generate',
            status: $status,
            latencyMs: (int) round((microtime(true) - $startedAt) * 1000),
            entityId: $eventId > 0 ? $eventId : null,
            retryCount: max(0, $retryCount)
        );
    }

    private function emitMetrics(
        DescriptionGenerationRun $run,
        DescriptionGenerationRunMetricsService $metricsService
    ): void {
        $metrics = $metricsService->summarize($run);

        Log::info('Event description generation run completed.', [
            'run_id' => $run->id,
            'status' => $run->status,
            'duration_seconds' => $metrics['duration_seconds'],
            'average_seconds_per_event' => $metrics['average_seconds_per_event'],
            'throughput_events_per_minute' => $metrics['throughput_events_per_minute'],
            'suggested_concurrency' => $metrics['suggested_concurrency'],
        ]);

        if (app()->environment('testing') || PHP_SAPI !== 'cli' || ! defined('STDOUT')) {
            return;
        }

        $lines = [
            sprintf('Run #%d completed with status=%s', (int) $run->id, (string) $run->status),
            sprintf('Total duration: %.2fs', (float) $metrics['duration_seconds']),
            sprintf('Average seconds/event: %.2f', (float) $metrics['average_seconds_per_event']),
            sprintf('Estimated throughput: %.2f events/minute', (float) $metrics['throughput_events_per_minute']),
            sprintf('Suggested concurrency: %d', (int) $metrics['suggested_concurrency']),
        ];

        foreach ($lines as $line) {
            fwrite(STDOUT, $line . PHP_EOL);
        }
    }
}
