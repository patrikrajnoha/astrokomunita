<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateEventDescriptionJob;
use App\Models\DescriptionGenerationRun;
use App\Models\Event;
use App\Services\Admin\AiLastRunStore;
use App\Services\Events\EventDescriptionGeneratorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminAiController extends Controller
{
    private const DESCRIPTIONS_QUEUE = 'descriptions';

    public function __construct(
        private readonly AiLastRunStore $lastRunStore,
        private readonly EventDescriptionGeneratorService $generatorService,
    ) {
    }

    public function config(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'event_id' => ['nullable', 'integer', 'min:1'],
        ]);

        $eventId = isset($validated['event_id']) ? (int) $validated['event_id'] : null;
        $humanizedPilotEnabled = (bool) config('events.ai.humanized_pilot_enabled', false);

        return response()->json([
            'data' => [
                'events_ai_humanized_enabled' => $humanizedPilotEnabled,
                'features' => [
                    'event_description_generate' => [
                        'last_run' => $this->lastRunStore->get('event_description_generate', $eventId),
                    ],
                ],
            ],
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
        // GenerateEventDescriptionJob already delegates transport retries to OllamaClient.
        return 1;
    }

    /**
     * @return array<int,int>
     */
    private function resolveRetryBackoffSeconds(int $attempts): array
    {
        return array_fill(0, max(1, $attempts), 0);
    }

    private function resolveConcurrency(): int
    {
        return max(1, min((int) config('ai.ollama_safe_concurrency_default', 1), 3));
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

