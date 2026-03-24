<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateEventDescriptionJob;
use App\Models\DescriptionGenerationRun;
use App\Models\Event;
use App\Services\Admin\AiLastRunStore;
use App\Services\Events\EventAiPolicyService;
use App\Services\Events\EventDescriptionGeneratorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Throwable;

class AdminAiController extends Controller
{
    private const DESCRIPTIONS_QUEUE = 'descriptions';

    public function __construct(
        private readonly AiLastRunStore $lastRunStore,
        private readonly EventDescriptionGeneratorService $generatorService,
        private readonly EventAiPolicyService $eventAiPolicyService,
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

    public function policy(): JsonResponse
    {
        return response()->json([
            'data' => $this->eventAiPolicyService->payload(),
        ]);
    }

    public function updatePolicy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'reset' => ['nullable', 'boolean'],
            'policy' => ['nullable', 'array'],

            'policy.prompts' => ['nullable', 'array'],
            'policy.prompts.legacy' => ['nullable', 'array'],
            'policy.prompts.legacy.rules' => ['nullable', 'array'],
            'policy.prompts.legacy.rules.*' => ['string', 'max:600'],
            'policy.prompts.humanized' => ['nullable', 'array'],
            'policy.prompts.humanized.rules' => ['nullable', 'array'],
            'policy.prompts.humanized.rules.*' => ['string', 'max:600'],

            'policy.safety' => ['nullable', 'array'],
            'policy.safety.numeric_token_guard_enabled' => ['nullable', 'boolean'],
            'policy.safety.celestial_term_guard_enabled' => ['nullable', 'boolean'],
            'policy.safety.artifact_guard_enabled' => ['nullable', 'boolean'],
            'policy.safety.celestial_terms' => ['nullable', 'array'],
            'policy.safety.celestial_terms.*' => ['string', 'max:80'],
            'policy.safety.forbidden_substrings' => ['nullable', 'array'],
            'policy.safety.forbidden_substrings.*' => ['string', 'max:160'],
            'policy.safety.forbidden_regex' => ['nullable', 'array'],
            'policy.safety.forbidden_regex.*' => ['string', 'max:260'],
        ]);

        $reset = (bool) ($validated['reset'] ?? false);
        $policyPatch = is_array($validated['policy'] ?? null)
            ? (array) $validated['policy']
            : [];

        if (! $reset && $policyPatch === []) {
            throw ValidationException::withMessages([
                'policy' => ['Policy patch is required unless reset=true.'],
            ]);
        }

        $this->validateRegexPatterns((array) data_get($policyPatch, 'safety.forbidden_regex', []));

        return response()->json([
            'data' => $this->eventAiPolicyService->update($policyPatch, $reset),
        ]);
    }

    public function generateEventDescription(Request $request, Event $event): JsonResponse
    {
        $validated = $request->validate([
            'sync' => ['nullable', 'boolean'],
            'mode' => ['nullable', 'string', 'in:ollama,template'],
            'fallback' => ['nullable', 'string', 'in:base,skip'],
            'force' => ['nullable', 'boolean'],
            'dry_run' => ['nullable', 'boolean'],
        ]);

        $sync = (bool) ($validated['sync'] ?? true);
        $requestedMode = strtolower(trim((string) ($validated['mode'] ?? 'ollama')));
        $fallbackMode = strtolower(trim((string) ($validated['fallback'] ?? 'base')));
        $force = (bool) ($validated['force'] ?? true);
        $dryRun = (bool) ($validated['dry_run'] ?? false);

        if ($dryRun && ! $sync) {
            throw ValidationException::withMessages([
                'dry_run' => ['Dry-run is supported only for sync=true.'],
            ]);
        }

        if ($sync && $dryRun) {
            return $this->runSingleEventDryRun(
                event: $event,
                requestedMode: $requestedMode,
                fallbackMode: $fallbackMode
            );
        }

        $retryAttempts = $this->resolveRetryAttempts();
        $retryBackoffSeconds = $this->resolveRetryBackoffSeconds($retryAttempts);
        $concurrency = $this->resolveConcurrency();

        $run = $this->createSingleEventRun(
            eventId: (int) $event->id,
            requestedMode: $requestedMode,
            fallbackMode: $fallbackMode,
            force: $force,
            dryRun: $dryRun
        );

        if ($sync) {
            GenerateEventDescriptionJob::dispatchSync(
                runId: (int) $run->id,
                eventId: (int) $event->id,
                force: $force,
                dryRun: $dryRun,
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
            dryRun: $dryRun,
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
        bool $force,
        bool $dryRun
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
            'dry_run' => $dryRun,
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
                'trigger' => $dryRun ? 'admin_events_generate_description_dry_run' : 'admin_events_generate_description',
            ],
            'error_message' => null,
        ]);
    }

    private function runSingleEventDryRun(Event $event, string $requestedMode, string $fallbackMode): JsonResponse
    {
        $startedAt = microtime(true);
        $eventId = (int) $event->id;

        try {
            $preview = $this->generatorService->generateForEvent($event, $requestedMode);
        } catch (Throwable $exception) {
            if ($requestedMode === 'ollama' && $fallbackMode === 'base') {
                $preview = $this->generatorService->generateForEvent($event, 'template');
            } else {
                $lastRun = $this->lastRunStore->put(
                    featureName: 'event_description_generate',
                    status: 'error',
                    latencyMs: (int) round((microtime(true) - $startedAt) * 1000),
                    entityId: $eventId,
                    retryCount: 0
                );

                return response()->json([
                    'message' => 'AI test opisu zlyhal.',
                    'error_code' => 'AI_DRY_RUN_FAILED',
                    'last_run' => $lastRun,
                ], 422);
            }
        }

        $provider = strtolower(trim((string) ($preview['provider'] ?? '')));
        $fallbackUsed = $this->isFallbackProvider($provider);
        $lastRun = $this->lastRunStore->put(
            featureName: 'event_description_generate',
            status: $fallbackUsed ? 'fallback' : 'success',
            latencyMs: (int) round((microtime(true) - $startedAt) * 1000),
            entityId: $eventId,
            retryCount: 0
        );

        return response()->json([
            'status' => 'done',
            'dry_run' => true,
            'job_id' => null,
            'data' => [
                'event_id' => $eventId,
                'description' => trim((string) ($preview['description'] ?? '')),
                'short' => trim((string) ($preview['short'] ?? '')),
                'fallback_used' => $fallbackUsed,
                'dry_run' => true,
                'provider' => $provider !== '' ? $provider : null,
            ],
            'last_run' => $lastRun,
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

    private function isFallbackProvider(string $provider): bool
    {
        $normalized = strtolower(trim($provider));

        return $normalized !== ''
            && (str_contains($normalized, 'fallback') || str_starts_with($normalized, 'template'));
    }

    /**
     * @param array<int,mixed> $patterns
     */
    private function validateRegexPatterns(array $patterns): void
    {
        $errors = [];
        foreach ($patterns as $index => $raw) {
            if (! is_string($raw)) {
                continue;
            }

            $pattern = trim($raw);
            if ($pattern === '') {
                continue;
            }

            if (! EventAiPolicyService::isRegexPatternValid($pattern)) {
                $errors['policy.safety.forbidden_regex.' . $index] = [
                    'Invalid regex pattern.',
                ];
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}

