<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateEventDescriptionJob;
use App\Models\DescriptionGenerationRun;
use App\Models\Event;
use App\Services\Admin\AiLastRunStore;
use App\Services\Events\EventDescriptionGeneratorService;
use App\Services\Events\EventInsightsCacheService;
use App\Services\Newsletter\NewsletterAiDraftService;
use App\Services\Newsletter\NewsletterSelectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class AdminAiController extends Controller
{
    private const DESCRIPTIONS_QUEUE = 'descriptions';
    private const PRIME_INSIGHTS_LOCK_KEY = 'ai:prime_insights:lock';

    public function __construct(
        private readonly AiLastRunStore $lastRunStore,
        private readonly EventInsightsCacheService $insightsCache,
        private readonly EventDescriptionGeneratorService $generatorService,
        private readonly NewsletterSelectionService $newsletterSelectionService,
        private readonly NewsletterAiDraftService $newsletterAiDraftService,
    ) {
    }

    public function config(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'event_id' => ['nullable', 'integer', 'min:1'],
        ]);

        $eventId = isset($validated['event_id']) ? (int) $validated['event_id'] : null;
        $humanizedPilotEnabled = (bool) config('events.ai.humanized_pilot_enabled', false);
        $newsletterCopyDraftEnabled = (bool) config('events.ai.newsletter_copy_draft_admin_enabled', false);

        return response()->json([
            'data' => [
                'events_ai_humanized_enabled' => $humanizedPilotEnabled,
                'insights_cache_ttl_seconds' => $this->insightsCache->ttlSeconds(),
                'prime_insights_max_limit' => $this->resolvePrimeInsightsMaxLimit(),
                'app_timezone' => (string) config('app.timezone', 'Europe/Bratislava'),
                'features' => [
                    'event_description_generate' => [
                        'enabled' => $humanizedPilotEnabled,
                        'pilot_label' => 'pilot',
                        'last_run' => $this->lastRunStore->get('event_description_generate', $eventId),
                    ],
                    'newsletter_prime_insights' => [
                        'enabled' => $humanizedPilotEnabled,
                        'pilot_label' => 'pilot',
                        'last_run' => $this->lastRunStore->get('newsletter_prime_insights'),
                    ],
                    'newsletter_copy_draft' => [
                        'enabled' => $newsletterCopyDraftEnabled,
                        'pilot_label' => 'pilot',
                        'last_run' => $this->lastRunStore->get('newsletter_copy_draft', 'newsletter'),
                    ],
                ],
            ],
        ]);
    }

    public function draftNewsletterCopy(Request $request): JsonResponse
    {
        if (! (bool) config('events.ai.newsletter_copy_draft_admin_enabled', false)) {
            return response()->json([
                'message' => 'AI draft newsletteru je vypnuty.',
            ], 403);
        }

        $result = $this->newsletterAiDraftService->generateDraft();

        return response()->json([
            'status' => (string) ($result['status'] ?? 'error'),
            'subjects' => array_values((array) ($result['subjects'] ?? [])),
            'intro' => (string) ($result['intro'] ?? ''),
            'tip_text' => (string) ($result['tip_text'] ?? ''),
            'fallback_used' => (bool) ($result['fallback_used'] ?? true),
            'last_run' => (array) ($result['last_run'] ?? []),
        ]);
    }

    public function generateEventDescription(Request $request, Event $event): JsonResponse
    {
        $validated = $request->validate([
            'sync' => ['nullable', 'boolean'],
            'mode' => ['nullable', 'string', 'in:ollama,template'],
            'fallback' => ['nullable', 'string', 'in:base,skip'],
            'force' => ['nullable', 'boolean'],
        ]);

        $sync = (bool) ($validated['sync'] ?? true);
        $requestedMode = strtolower(trim((string) ($validated['mode'] ?? 'ollama')));
        $fallbackMode = strtolower(trim((string) ($validated['fallback'] ?? 'base')));
        $force = (bool) ($validated['force'] ?? true);
        $retryAttempts = $this->resolveRetryAttempts();
        $retryBackoffSeconds = $this->resolveRetryBackoffSeconds($retryAttempts);
        $concurrency = $this->resolveConcurrency();

        $run = $this->createSingleEventRun(
            eventId: (int) $event->id,
            requestedMode: $requestedMode,
            fallbackMode: $fallbackMode,
            force: $force
        );

        if ($sync) {
            GenerateEventDescriptionJob::dispatchSync(
                runId: (int) $run->id,
                eventId: (int) $event->id,
                force: $force,
                dryRun: false,
                requestedMode: $requestedMode,
                fallbackMode: $fallbackMode,
                retryAttempts: $retryAttempts,
                retryBackoffSeconds: $retryBackoffSeconds,
                concurrency: $concurrency
            );

            $event->refresh();
            $run->refresh();

            $lastRun = $this->lastRunStore->get('event_description_generate', (int) $event->id);
            if (! $lastRun) {
                $lastRun = $this->lastRunStore->put(
                    featureName: 'event_description_generate',
                    status: $this->statusFromRun($run),
                    latencyMs: null,
                    entityId: (int) $event->id,
                    retryCount: max(0, ((int) data_get($run->meta, 'last_attempts', 1)) - 1)
                );
            }

            $eventStatus = (string) data_get($run->meta, 'last_event_status', '');

            return response()->json([
                'status' => 'done',
                'job_id' => (int) $run->id,
                'data' => [
                    'event_id' => (int) $event->id,
                    'description' => trim((string) ($event->description ?? '')),
                    'short' => trim((string) ($event->short ?? '')),
                    'fallback_used' => $this->isFallbackEventStatus($eventStatus),
                ],
                'last_run' => $lastRun,
            ]);
        }

        GenerateEventDescriptionJob::dispatch(
            runId: (int) $run->id,
            eventId: (int) $event->id,
            force: $force,
            dryRun: false,
            requestedMode: $requestedMode,
            fallbackMode: $fallbackMode,
            retryAttempts: $retryAttempts,
            retryBackoffSeconds: $retryBackoffSeconds,
            concurrency: $concurrency
        )->onQueue(self::DESCRIPTIONS_QUEUE);

        $lastRun = $this->lastRunStore->put(
            featureName: 'event_description_generate',
            status: 'idle',
            latencyMs: null,
            entityId: (int) $event->id,
            retryCount: 0
        );

        return response()->json([
            'status' => 'accepted',
            'job_id' => (int) $run->id,
            'last_run' => $lastRun,
        ], 202);
    }

    public function primeNewsletterInsights(Request $request): JsonResponse
    {
        $maxLimit = $this->resolvePrimeInsightsMaxLimit();
        $validated = $request->validate([
            'limit' => ['nullable', 'integer', 'min:1', 'max:' . $maxLimit],
        ]);

        $defaultLimit = max(1, min((int) config('events.ai.prime_insights_default_limit', 5), $maxLimit));
        $limit = max(1, min((int) ($validated['limit'] ?? $defaultLimit), $maxLimit));
        $startedAt = microtime(true);
        $lockTtlSeconds = $this->resolvePrimeInsightsLockTtlSeconds();

        $lockAcquired = Cache::add(
            self::PRIME_INSIGHTS_LOCK_KEY,
            [
                'locked_at_unix' => now()->getTimestamp(),
                'lock_ttl_seconds' => $lockTtlSeconds,
            ],
            now()->addSeconds($lockTtlSeconds)
        );
        if (! $lockAcquired) {
            $retryAfterSeconds = $this->resolvePrimeInsightsRetryAfterSeconds($lockTtlSeconds);

            return response()->json([
                'status' => 'locked',
                'message' => 'Priprava insights uz prebieha. Skuste to o chvilu znova.',
                'retry_after_seconds' => $retryAfterSeconds,
            ], 409);
        }

        $payload = $this->newsletterSelectionService->buildNewsletterPayload(adminPreview: true);
        $eventIds = collect((array) ($payload['top_events'] ?? []))
            ->map(static fn (mixed $row): int => (int) data_get($row, 'id', 0))
            ->filter(static fn (int $eventId): bool => $eventId > 0)
            ->take($limit)
            ->values()
            ->all();

        $processed = 0;
        $primed = 0;
        $fallbackCount = 0;
        $failed = 0;

        foreach ($eventIds as $eventId) {
            $event = Event::query()->find($eventId);
            if (! $event) {
                continue;
            }

            $processed++;

            try {
                $result = $this->generatorService->generateForEvent($event, 'ollama');
                $insights = is_array($result['insights'] ?? null)
                    ? (array) $result['insights']
                    : [];

                $whyInteresting = trim((string) ($insights['why_interesting'] ?? ''));
                $howToObserve = trim((string) ($insights['how_to_observe'] ?? ''));

                if ($whyInteresting === '' && $howToObserve === '') {
                    $fallbackCount++;
                    continue;
                }

                $this->insightsCache->put(
                    event: $event,
                    whyInteresting: $whyInteresting,
                    howToObserve: $howToObserve
                );
                $primed++;
            } catch (Throwable $exception) {
                $failed++;
                Log::warning('Admin newsletter insights priming failed.', [
                    'event_id' => (int) $eventId,
                    'error_class' => get_class($exception),
                ]);
            }
        }

        $status = 'success';
        if ($failed > 0 && $primed === 0) {
            $status = 'error';
        } elseif ($failed > 0 || $fallbackCount > 0) {
            $status = 'fallback';
        }

        $lastRun = $this->lastRunStore->put(
            featureName: 'newsletter_prime_insights',
            status: $status,
            latencyMs: (int) round((microtime(true) - $startedAt) * 1000),
            entityId: null,
            retryCount: 0
        );

        return response()->json([
            'status' => 'done',
            'job_id' => null,
            'data' => [
                'requested_limit' => $limit,
                'processed' => $processed,
                'primed' => $primed,
                'fallback' => $fallbackCount,
                'failed' => $failed,
            ],
            'last_run' => $lastRun,
        ]);
    }

    private function createSingleEventRun(
        int $eventId,
        string $requestedMode,
        string $fallbackMode,
        bool $force
    ): DescriptionGenerationRun {
        return DescriptionGenerationRun::query()->create([
            'started_at' => now(),
            'finished_at' => null,
            'status' => 'running',
            'requested_mode' => $requestedMode,
            'effective_mode' => $requestedMode,
            'fallback_mode' => $fallbackMode,
            'resume_enabled' => false,
            'force_enabled' => $force,
            'dry_run' => false,
            'from_id' => $eventId,
            'limit' => 1,
            'last_event_id' => 0,
            'processed' => 0,
            'generated' => 0,
            'failed' => 0,
            'skipped' => 0,
            'meta' => [
                'queue' => self::DESCRIPTIONS_QUEUE,
                'queued_total' => 1,
                'target_processed' => 1,
                'event_id' => $eventId,
                'trigger' => 'admin_events_generate_description',
            ],
            'error_message' => null,
        ]);
    }

    private function resolveRetryAttempts(): int
    {
        return max(1, (int) config('ai.ollama_retry_attempts', 3));
    }

    /**
     * @return array<int,int>
     */
    private function resolveRetryBackoffSeconds(int $attempts): array
    {
        $configured = config('ai.ollama_retry_backoff_seconds', []);
        $sequence = is_array($configured)
            ? array_values(array_filter(array_map(
                static fn (mixed $value): int => max(0, (int) $value),
                $configured
            ), static fn (int $value): bool => $value >= 0))
            : [];

        if ($sequence === []) {
            $sequence = [1, 3, 7];
        }

        while (count($sequence) < $attempts) {
            $sequence[] = (int) end($sequence);
        }

        return array_slice($sequence, 0, $attempts);
    }

    private function resolveConcurrency(): int
    {
        return max(1, min((int) config('ai.ollama_safe_concurrency_default', 1), 3));
    }

    private function resolvePrimeInsightsMaxLimit(): int
    {
        return max(1, min((int) config('events.ai.prime_insights_max_limit', 10), 10));
    }

    private function resolvePrimeInsightsLockTtlSeconds(): int
    {
        return max(10, min((int) config('events.ai.prime_insights_lock_ttl_seconds', 60), 300));
    }

    private function resolvePrimeInsightsRetryAfterSeconds(int $fallbackSeconds): int
    {
        $fallback = max(1, $fallbackSeconds);
        $lockPayload = Cache::get(self::PRIME_INSIGHTS_LOCK_KEY);

        if (! is_array($lockPayload)) {
            return $fallback;
        }

        $lockedAtUnix = (int) ($lockPayload['locked_at_unix'] ?? 0);
        if ($lockedAtUnix <= 0) {
            return $fallback;
        }

        $ttlSeconds = max(1, (int) ($lockPayload['lock_ttl_seconds'] ?? $fallback));
        $remainingSeconds = ($lockedAtUnix + $ttlSeconds) - now()->getTimestamp();

        return $remainingSeconds > 0 ? $remainingSeconds : $fallback;
    }

    private function statusFromRun(DescriptionGenerationRun $run): string
    {
        $eventStatus = (string) data_get($run->meta, 'last_event_status', '');
        if ((int) $run->failed > 0 || $eventStatus === 'failed') {
            return 'error';
        }

        return $this->isFallbackEventStatus($eventStatus) ? 'fallback' : 'success';
    }

    private function isFallbackEventStatus(string $eventStatus): bool
    {
        $normalized = strtolower(trim($eventStatus));

        return $normalized !== ''
            && (str_contains($normalized, 'fallback') || str_starts_with($normalized, 'template'));
    }
}

