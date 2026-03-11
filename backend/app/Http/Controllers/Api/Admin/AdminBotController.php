<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\BotPublishStatus;
use App\Enums\BotRunFailureReason;
use App\Enums\BotTranslationStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Admin\Concerns\SerializesBotAdminData;
use App\Models\BotActivityLog;
use App\Models\BotItem;
use App\Models\BotRun;
use App\Models\BotSchedule;
use App\Models\BotSource;
use App\Models\Post;
use App\Models\User;
use App\Services\Bots\BotPostTranslationBackfillService;
use App\Services\Bots\BotPostRetentionService;
use App\Services\Bots\BotOverviewService;
use App\Services\Bots\Contracts\BotTranslationServiceInterface;
use App\Services\Bots\BotPublisherService;
use App\Services\Bots\BotRunner;
use App\Services\Bots\BotSourceHealthPolicy;
use App\Services\Bots\BotSourceHealthService;
use App\Services\Bots\BotSourceSyncService;
use App\Services\Translation\Exceptions\TranslationProviderUnavailableException;
use App\Services\Translation\Exceptions\TranslationTimeoutException;
use App\Services\Translation\TranslationOutageSimulationService;
use App\Services\PostService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class AdminBotController extends Controller
{
    use SerializesBotAdminData;

    public function __construct(
        private readonly BotRunner $runner,
        private readonly BotPublisherService $publisherService,
        private readonly PostService $postService,
        private readonly BotTranslationServiceInterface $translationService,
        private readonly BotPostTranslationBackfillService $backfillService,
        private readonly BotSourceSyncService $botSourceSyncService,
        private readonly BotOverviewService $botOverviewService,
        private readonly BotSourceHealthPolicy $botSourceHealthPolicy,
        private readonly BotSourceHealthService $botSourceHealthService,
        private readonly TranslationOutageSimulationService $outageSimulationService,
        private readonly BotPostRetentionService $botPostRetentionService,
    ) {
    }

    public function overview(): JsonResponse
    {
        return response()->json($this->botOverviewService->buildOverview());
    }

    public function sources(Request $request): JsonResponse
    {
        $this->botSourceSyncService->syncDefaults();

        $validated = $request->validate([
            'enabled' => 'nullable|boolean',
            'failing_only' => 'nullable|boolean',
            'q' => 'nullable|string|max:120',
        ]);

        $query = BotSource::query();

        if (array_key_exists('enabled', $validated)) {
            $query->where('is_enabled', (bool) $validated['enabled']);
        }
        if (($validated['failing_only'] ?? false) === true) {
            $query->where('consecutive_failures', '>', 0);
        }

        $search = strtolower(trim((string) ($validated['q'] ?? '')));
        if ($search !== '') {
            $query->where(function (Builder $sourceQuery) use ($search): void {
                $sourceQuery
                    ->whereRaw('LOWER(COALESCE(name, "")) like ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(key) like ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(url) like ?', ["%{$search}%"]);
            });
        }

        $sources = $query
            ->orderBy('key')
            ->get();

        $sourceMetrics = $this->sourceMetricsBySourceId($sources);
        $data = $sources->map(fn (BotSource $source): array => $this->serializeSource(
            $source,
            $sourceMetrics[$source->id] ?? []
        ))->values();

        return response()->json([
            'data' => $data,
        ]);
    }

    public function updateSource(Request $request, int $sourceId): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|nullable|string|max:160',
            'url' => 'sometimes|string|max:2048|url',
            'is_enabled' => 'sometimes|boolean',
        ]);

        $source = BotSource::query()->find($sourceId);
        if (!$source) {
            return response()->json([
                'message' => 'Zdroj bota sa nenasiel.',
            ], 404);
        }

        $updates = [];
        if (array_key_exists('name', $validated)) {
            $updates['name'] = $this->nullableString($validated['name']);
        }
        if (array_key_exists('url', $validated)) {
            $updates['url'] = trim((string) $validated['url']);
        }
        if (array_key_exists('is_enabled', $validated)) {
            $updates['is_enabled'] = (bool) $validated['is_enabled'];
        }

        if ($updates !== []) {
            $source->fill($updates)->save();
        }

        $metrics = $this->sourceMetricsBySourceId(collect([$source->fresh() ?? $source]));

        return response()->json([
            'data' => $this->serializeSource($source->fresh() ?? $source, $metrics[$source->id] ?? []),
        ]);
    }

    public function resetSourceHealth(int $sourceId): JsonResponse
    {
        $source = BotSource::query()->find($sourceId);
        if (!$source) {
            return response()->json([
                'message' => 'Zdroj bota sa nenasiel.',
            ], 404);
        }

        $this->botSourceHealthService->resetHealth($source);
        $fresh = $source->fresh() ?? $source;
        $metrics = $this->sourceMetricsBySourceId(collect([$fresh]));

        return response()->json([
            'data' => $this->serializeSource($fresh, $metrics[$fresh->id] ?? []),
        ]);
    }

    public function clearSourceCooldown(int $sourceId): JsonResponse
    {
        $source = BotSource::query()->find($sourceId);
        if (!$source) {
            return response()->json([
                'message' => 'Zdroj bota sa nenasiel.',
            ], 404);
        }

        $this->botSourceHealthService->clearCooldown($source);
        $fresh = $source->fresh() ?? $source;
        $metrics = $this->sourceMetricsBySourceId(collect([$fresh]));

        return response()->json([
            'data' => $this->serializeSource($fresh, $metrics[$fresh->id] ?? []),
        ]);
    }

    public function reviveSource(int $sourceId): JsonResponse
    {
        $source = BotSource::query()->find($sourceId);
        if (!$source) {
            return response()->json([
                'message' => 'Zdroj bota sa nenasiel.',
            ], 404);
        }

        $this->botSourceHealthService->revive($source);
        $fresh = $source->fresh() ?? $source;
        $metrics = $this->sourceMetricsBySourceId(collect([$fresh]));

        return response()->json([
            'data' => $this->serializeSource($fresh, $metrics[$fresh->id] ?? []),
        ]);
    }

    public function runs(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'sourceKey' => 'nullable|string|max:120',
            'bot_identity' => 'nullable|string|max:20',
            'status' => 'nullable|string|max:20',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'per_page' => 'nullable|integer|min:1|max:50',
        ]);

        $query = BotRun::query()
            ->with('source:id,key');

        $sourceKey = strtolower(trim((string) ($validated['sourceKey'] ?? '')));
        if ($sourceKey !== '') {
            $query->whereHas('source', function ($sourceQuery) use ($sourceKey): void {
                $sourceQuery->where('key', $sourceKey);
            });
        }

        $botIdentity = strtolower(trim((string) ($validated['bot_identity'] ?? '')));
        if ($botIdentity !== '') {
            $query->where('bot_identity', $botIdentity);
        }

        $status = strtolower(trim((string) ($validated['status'] ?? '')));
        if ($status !== '') {
            $query->where('status', $status);
        }

        $dateFrom = trim((string) ($validated['date_from'] ?? ''));
        if ($dateFrom !== '') {
            $query->where('started_at', '>=', Carbon::parse($dateFrom)->startOfDay());
        }

        $dateTo = trim((string) ($validated['date_to'] ?? ''));
        if ($dateTo !== '') {
            $query->where('started_at', '<=', Carbon::parse($dateTo)->endOfDay());
        }

        $perPage = (int) ($validated['per_page'] ?? 20);
        $paginator = $query
            ->orderByDesc('started_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        $paginator->setCollection(
            $paginator->getCollection()->map(fn (BotRun $run): array => $this->serializeRun($run))
        );

        return response()->json($paginator);
    }

    public function activity(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'sourceKey' => 'nullable|string|max:120',
            'bot_identity' => 'nullable|string|max:20',
            'action' => 'nullable|string|max:50',
            'outcome' => 'nullable|string|max:20',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = BotActivityLog::query()
            ->with([
                'source:id,key',
                'run:id,status,started_at,finished_at',
                'item:id,stable_key,publish_status,translation_status',
            ]);

        $sourceKey = strtolower(trim((string) ($validated['sourceKey'] ?? '')));
        if ($sourceKey !== '') {
            $query->whereHas('source', function (Builder $sourceQuery) use ($sourceKey): void {
                $sourceQuery->where('key', $sourceKey);
            });
        }

        $botIdentity = strtolower(trim((string) ($validated['bot_identity'] ?? '')));
        if ($botIdentity !== '') {
            $query->where('bot_identity', $botIdentity);
        }

        $action = strtolower(trim((string) ($validated['action'] ?? '')));
        if ($action !== '') {
            $query->where('action', $action);
        }

        $outcome = strtolower(trim((string) ($validated['outcome'] ?? '')));
        if ($outcome !== '') {
            $query->where('outcome', $outcome);
        }

        $dateFrom = trim((string) ($validated['date_from'] ?? ''));
        if ($dateFrom !== '') {
            $query->where('created_at', '>=', Carbon::parse($dateFrom)->startOfDay());
        }

        $dateTo = trim((string) ($validated['date_to'] ?? ''));
        if ($dateTo !== '') {
            $query->where('created_at', '<=', Carbon::parse($dateTo)->endOfDay());
        }

        $perPage = (int) ($validated['per_page'] ?? 30);
        $paginator = $query
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        $paginator->setCollection(
            $paginator->getCollection()->map(fn (BotActivityLog $log): array => $this->serializeActivity($log))
        );

        return response()->json($paginator);
    }

    public function schedules(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'enabled' => 'nullable|boolean',
            'bot_user_id' => 'nullable|integer|min:1',
            'source_id' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = BotSchedule::query()
            ->with([
                'botUser:id,username,role,is_bot',
                'source:id,key,name,bot_identity,source_type,is_enabled',
            ]);

        if (array_key_exists('enabled', $validated)) {
            $query->where('enabled', (bool) $validated['enabled']);
        }
        if (isset($validated['bot_user_id'])) {
            $query->where('bot_user_id', (int) $validated['bot_user_id']);
        }
        if (isset($validated['source_id'])) {
            $query->where('source_id', (int) $validated['source_id']);
        }

        $perPage = (int) ($validated['per_page'] ?? 30);
        $paginator = $query
            ->orderBy('next_run_at')
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();

        $paginator->setCollection(
            $paginator->getCollection()->map(fn (BotSchedule $schedule): array => $this->serializeSchedule($schedule))
        );

        return response()->json($paginator);
    }

    public function createSchedule(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'bot_user_id' => 'required|integer|min:1|exists:users,id',
            'source_id' => 'nullable|integer|min:1|exists:bot_sources,id',
            'enabled' => 'sometimes|boolean',
            'interval_minutes' => 'required|integer|min:1|max:10080',
            'jitter_seconds' => 'nullable|integer|min:0|max:86400',
            'timezone' => 'nullable|string|timezone|max:64',
        ]);

        $botUser = User::query()->findOrFail((int) $validated['bot_user_id']);
        if (!((bool) $botUser->is_bot || strtolower(trim((string) $botUser->role)) === User::ROLE_BOT)) {
            throw ValidationException::withMessages([
                'bot_user_id' => 'Selected user is not a bot account.',
            ]);
        }

        $source = null;
        if (!empty($validated['source_id'])) {
            $source = BotSource::query()->find((int) $validated['source_id']);
            if (!$source) {
                throw ValidationException::withMessages([
                    'source_id' => 'Selected source is invalid.',
                ]);
            }
        }

        $schedule = BotSchedule::query()->create([
            'bot_user_id' => $botUser->id,
            'source_id' => $source?->id,
            'enabled' => (bool) ($validated['enabled'] ?? true),
            'interval_minutes' => (int) $validated['interval_minutes'],
            'jitter_seconds' => (int) ($validated['jitter_seconds'] ?? 0),
            'timezone' => $this->nullableString($validated['timezone'] ?? null),
            'next_run_at' => now(),
            'last_run_at' => null,
            'last_result' => null,
            'last_message' => null,
        ]);

        return response()->json([
            'data' => $this->serializeSchedule($schedule->fresh(['botUser:id,username,role,is_bot', 'source:id,key,name,bot_identity,source_type,is_enabled']) ?? $schedule),
        ], 201);
    }

    public function updateSchedule(Request $request, int $scheduleId): JsonResponse
    {
        $validated = $request->validate([
            'enabled' => 'sometimes|boolean',
            'interval_minutes' => 'sometimes|integer|min:1|max:10080',
            'jitter_seconds' => 'sometimes|integer|min:0|max:86400',
            'timezone' => 'sometimes|nullable|string|timezone|max:64',
            'source_id' => 'sometimes|nullable|integer|min:1|exists:bot_sources,id',
        ]);

        $schedule = BotSchedule::query()->find($scheduleId);
        if (!$schedule) {
            return response()->json([
                'message' => 'Plan bota sa nenasiel.',
            ], 404);
        }

        if (array_key_exists('source_id', $validated)) {
            $schedule->source_id = $validated['source_id'] !== null
                ? (int) $validated['source_id']
                : null;
        }
        if (array_key_exists('enabled', $validated)) {
            $schedule->enabled = (bool) $validated['enabled'];
        }
        if (array_key_exists('interval_minutes', $validated)) {
            $schedule->interval_minutes = (int) $validated['interval_minutes'];
        }
        if (array_key_exists('jitter_seconds', $validated)) {
            $schedule->jitter_seconds = (int) $validated['jitter_seconds'];
        }
        if (array_key_exists('timezone', $validated)) {
            $schedule->timezone = $this->nullableString($validated['timezone']);
        }

        $schedule->save();

        return response()->json([
            'data' => $this->serializeSchedule($schedule->fresh(['botUser:id,username,role,is_bot', 'source:id,key,name,bot_identity,source_type,is_enabled']) ?? $schedule),
        ]);
    }

    public function deleteSchedule(int $scheduleId): JsonResponse
    {
        $schedule = BotSchedule::query()->find($scheduleId);
        if (!$schedule) {
            return response()->json([
                'message' => 'Plan bota sa nenasiel.',
            ], 404);
        }

        $schedule->delete();

        return response()->json([
            'deleted' => true,
            'id' => $scheduleId,
        ]);
    }

    public function run(Request $request, string $sourceKey): JsonResponse
    {
        $executionBudget = max(30, (int) config('bots.run_max_execution_seconds', 120));
        if (function_exists('set_time_limit')) {
            @set_time_limit($executionBudget);
        }

        $normalizedSourceKey = strtolower(trim($sourceKey));
        $validated = $request->validate([
            'force_manual_override' => 'sometimes|boolean',
            'mode' => 'sometimes|string|in:auto,dry',
            'publish_limit' => 'nullable|integer|min:1|max:100',
        ]);
        $forceManualOverride = (bool) ($validated['force_manual_override'] ?? false);

        $source = BotSource::query()
            ->where('key', $normalizedSourceKey)
            ->first();

        if (!$source) {
            return response()->json([
                'message' => sprintf('Bot source "%s" was not found.', $normalizedSourceKey),
            ], 404);
        }

        if (!$source->is_enabled) {
            return response()->json([
                'message' => sprintf('Bot source "%s" is disabled.', $normalizedSourceKey),
            ], 422);
        }

        if (!$forceManualOverride) {
            $throttleSeconds = 120;
            $throttleKey = sprintf('bots:throttle:manual:%s', $normalizedSourceKey);
            $throttleExpiresAt = now()->addSeconds($throttleSeconds)->timestamp;
            if (!Cache::add($throttleKey, $throttleExpiresAt, $throttleSeconds)) {
                $retryAfter = max(1, (int) Cache::get($throttleKey, 0) - now()->timestamp);

                return response()->json([
                    'message' => sprintf('Manual run for "%s" is temporarily throttled.', $normalizedSourceKey),
                    'retry_after' => $retryAfter,
                ], 429);
            }
        }

        $mode = strtolower(trim((string) ($validated['mode'] ?? $this->defaultModeForSource($source->key))));
        if (!in_array($mode, ['auto', 'dry'], true)) {
            $mode = 'auto';
        }

        $publishLimit = isset($validated['publish_limit'])
            ? (int) $validated['publish_limit']
            : null;

        $run = $this->runner->run(
            $source,
            'admin',
            $forceManualOverride,
            $mode,
            $publishLimit
        );

        return response()->json([
            'run_id' => $run->id,
            'source_key' => $source->key,
            'status' => $run->status?->value ?? (string) $run->status,
            'stats' => is_array($run->stats) ? $run->stats : [],
            'meta' => is_array($run->meta) ? $run->meta : [],
            'error_text' => $this->truncateErrorText($run->error_text),
            'failure_reason' => BotRunFailureReason::fromNullable(data_get($run->meta, 'failure_reason'))->value,
            'ui_message' => $this->nullableString(data_get($run->meta, 'ui_message')),
            'cooldown_until' => $this->nullableString(data_get($run->meta, 'cooldown_until')),
        ]);
    }

    public function items(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'run_id' => 'nullable|integer|min:1|exists:bot_runs,id',
            'sourceKey' => 'nullable|string|max:120|required_without:run_id',
            'date' => 'nullable|date_format:Y-m-d|required_with:sourceKey|required_without:run_id',
            'per_page' => 'nullable|integer|min:1|max:100',
            'page' => 'nullable|integer|min:1',
        ]);

        $runId = isset($validated['run_id']) ? (int) $validated['run_id'] : null;
        $sourceId = null;
        $windowStart = null;
        $windowEnd = null;

        if ($runId !== null) {
            $run = BotRun::query()->find($runId);
            if (!$run) {
                throw ValidationException::withMessages([
                    'run_id' => 'Selected run_id is invalid.',
                ]);
            }

            $sourceId = (int) $run->source_id;
            $runLinkedItemsQuery = $this->runLinkedItemsQuery($run);
            $query = (clone $runLinkedItemsQuery)
                ->orderByDesc('fetched_at')
                ->orderByDesc('id');

            if (!(clone $runLinkedItemsQuery)->exists()) {
                [$windowStart, $windowEnd] = $this->resolveRunWindow($run);
                $query = BotItem::query()
                    ->where('source_id', $sourceId)
                    ->whereBetween('fetched_at', [$windowStart, $windowEnd])
                    ->orderByDesc('fetched_at')
                    ->orderByDesc('id');
            }
        } else {
            $sourceKey = strtolower(trim((string) ($validated['sourceKey'] ?? '')));
            $date = trim((string) ($validated['date'] ?? ''));

            if ($sourceKey === '' || $date === '') {
                throw ValidationException::withMessages([
                    'run_id' => 'Provide run_id or sourceKey and date.',
                ]);
            }

            $source = BotSource::query()->where('key', $sourceKey)->first();
            if (!$source) {
                return response()->json([
                    'message' => sprintf('Bot source "%s" was not found.', $sourceKey),
                ], 404);
            }

            $sourceId = (int) $source->id;
            $dateStart = Carbon::createFromFormat('Y-m-d', $date)->startOfDay();
            $windowStart = $dateStart->copy();
            $windowEnd = $dateStart->copy()->endOfDay();

            $query = BotItem::query()
                ->where('source_id', $sourceId)
                ->whereBetween('fetched_at', [$windowStart, $windowEnd])
                ->orderByDesc('fetched_at')
                ->orderByDesc('id');
        }

        $perPage = (int) ($validated['per_page'] ?? 20);
        $paginator = $query->paginate($perPage)->withQueryString();
        $paginator->setCollection(
            $paginator->getCollection()->map(fn (BotItem $item): array => $this->serializeItem($item))
        );

        return response()->json($paginator);
    }

    public function translationTest(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'text' => 'nullable|string|max:5000',
        ]);

        $text = trim((string) ($validated['text'] ?? 'The Solar System contains eight planets orbiting the Sun.'));
        if ($text === '') {
            $text = 'The Solar System contains eight planets orbiting the Sun.';
        }

        $startedAt = microtime(true);

        try {
            $result = $this->translationService->translate($text, null, 'sk');
        } catch (Throwable $e) {
            $failureReason = BotRunFailureReason::UNKNOWN->value;
            $statusCode = 422;

            if ($e instanceof TranslationTimeoutException) {
                $failureReason = BotRunFailureReason::TRANSLATION_TIMEOUT->value;
                $statusCode = 504;
            } elseif ($e instanceof TranslationProviderUnavailableException) {
                $failureReason = BotRunFailureReason::PROVIDER_UNAVAILABLE->value;
                $statusCode = 503;
            }

            return response()->json([
                'ok' => false,
                'message' => 'Test prekladu zlyhal.',
                'failure_reason' => $failureReason,
                'error' => $this->truncateErrorText($e->getMessage(), 300),
            ], $statusCode);
        }

        $meta = is_array($result['meta'] ?? null) ? $result['meta'] : [];
        $translatedText = trim((string) ($result['translated_title'] ?? $result['title_translated'] ?? ''));
        $durationMs = (int) round((microtime(true) - $startedAt) * 1000);
        $provider = trim((string) data_get($meta, 'provider', ''));

        return response()->json([
            'ok' => true,
            'provider' => $provider !== '' ? $provider : null,
            'latency_ms' => $durationMs,
            'status' => strtolower(trim((string) ($result['status'] ?? 'done'))),
            'translated_text' => $translatedText !== '' ? $translatedText : null,
            'mode' => $this->nullableString(data_get($meta, 'mode')),
            'quality_flags' => array_values(array_filter(
                array_map('strval', (array) data_get($meta, 'quality_flags', [])),
                static fn (string $flag): bool => trim($flag) !== ''
            )),
            'provider_chain' => array_values(array_filter(
                array_map('strval', (array) data_get($meta, 'provider_chain', [])),
                static fn (string $providerName): bool => trim($providerName) !== ''
            )),
            'meta' => [
                'target_lang' => strtolower(trim((string) data_get($meta, 'target_lang', 'sk'))),
                'model' => $this->nullableString(data_get($meta, 'model')),
                'fallback_used' => (bool) data_get($meta, 'fallback_used', false),
                'quality_retry_count' => (int) data_get($meta, 'quality_retry_count', 0),
            ],
        ]);
    }

    public function translationHealth(): JsonResponse
    {
        $provider = strtolower(trim((string) config('bots.translation.primary', 'libretranslate')));
        $fallbackProvider = strtolower(trim((string) config('bots.translation.fallback', 'none')));
        $timeoutSec = max(1, (int) config('bots.translation.timeout_sec', 12));
        $degraded = false;
        $simulateOutageProvider = $this->outageSimulationService->getProvider();

        $baseUrl = match ($provider) {
            'ollama' => trim((string) config('ai.ollama.base_url', config('ai.ollama_base_url', ''))),
            default => trim((string) config('bots.translation.libretranslate.url', '')),
        };

        try {
            $probeResult = $this->runTranslationHealthProbe();
            $result = [
                'ok' => true,
                'error_type' => null,
            ];
            $degraded = (bool) data_get($probeResult, 'meta.fallback_used', false);
        } catch (Throwable $exception) {
            $primaryErrorType = $this->translationHealthErrorType($exception);
            $hasFallback = $fallbackProvider !== '' && $fallbackProvider !== 'none' && $fallbackProvider !== $provider;

            if ($hasFallback) {
                try {
                    $this->runTranslationHealthProbe($fallbackProvider);
                    $degraded = true;
                    $result = [
                        'ok' => true,
                        'error_type' => null,
                        'primary_error_type' => $primaryErrorType,
                    ];
                } catch (Throwable $fallbackException) {
                    $result = [
                        'ok' => false,
                        'error_type' => $this->translationHealthErrorType($fallbackException),
                    ];
                }
            } else {
                $result = [
                    'ok' => false,
                    'error_type' => $primaryErrorType,
                ];
            }
        }

        $translationCounts = BotItem::query()
            ->selectRaw('translation_status, COUNT(*) as total')
            ->groupBy('translation_status')
            ->pluck('total', 'translation_status');
        $doneCount = (int) ($translationCounts[BotTranslationStatus::DONE->value] ?? 0);
        $skippedCount = (int) ($translationCounts[BotTranslationStatus::SKIPPED->value] ?? 0);
        $failedCount = (int) ($translationCounts[BotTranslationStatus::FAILED->value] ?? 0);
        $pendingCount = (int) ($translationCounts[BotTranslationStatus::PENDING->value] ?? 0);
        $processedCount = $doneCount + $skippedCount + $failedCount;
        $totalCount = $processedCount + $pendingCount;
        $progressPercent = $totalCount > 0
            ? (int) round(($processedCount / $totalCount) * 100)
            : 100;

        return response()->json([
            'provider' => $provider,
            'fallback_provider' => $fallbackProvider,
            'base_url' => $baseUrl !== '' ? $baseUrl : null,
            'timeout_sec' => $timeoutSec,
            'simulate_outage_provider' => $simulateOutageProvider,
            'degraded' => $degraded,
            'result' => $result,
            'translation_queue' => [
                'done' => $doneCount,
                'skipped' => $skippedCount,
                'failed' => $failedCount,
                'pending' => $pendingCount,
                'processed' => $processedCount,
                'total' => $totalCount,
                'progress_percent' => $progressPercent,
            ],
        ]);
    }

    public function updateTranslationSimulateOutage(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'provider' => 'required|string|in:none,ollama,libretranslate',
        ]);

        $admin = $request->user();
        $changed = $this->outageSimulationService->setProvider((string) $validated['provider']);

        Log::info('Admin updated translation outage simulation provider.', [
            'admin_user_id' => $admin?->id,
            'admin_email' => $admin?->email,
            'setting_key' => TranslationOutageSimulationService::SETTING_KEY,
            'old_value' => $changed['old'],
            'new_value' => $changed['new'],
        ]);

        return response()->json([
            'key' => TranslationOutageSimulationService::SETTING_KEY,
            'old_value' => $changed['old'],
            'new_value' => $changed['new'],
        ]);
    }

    /**
     * @return array<string,mixed>
     */
    private function runTranslationHealthProbe(?string $forceProvider = null): array
    {
        $originalPrimary = (string) config('bots.translation.primary', 'libretranslate');
        $originalFallback = (string) config('bots.translation.fallback', 'none');

        if ($forceProvider !== null) {
            config()->set('bots.translation.primary', strtolower(trim($forceProvider)));
            config()->set('bots.translation.fallback', 'none');
        }

        try {
            return $this->translationService->translate('health check', null, 'sk');
        } finally {
            if ($forceProvider !== null) {
                config()->set('bots.translation.primary', $originalPrimary);
                config()->set('bots.translation.fallback', $originalFallback);
            }
        }
    }

    private function translationHealthErrorType(Throwable $exception): string
    {
        if ($exception instanceof TranslationTimeoutException) {
            return BotRunFailureReason::TRANSLATION_TIMEOUT->value;
        }

        if ($exception instanceof TranslationProviderUnavailableException) {
            return BotRunFailureReason::PROVIDER_UNAVAILABLE->value;
        }

        return BotRunFailureReason::UNKNOWN->value;
    }

    public function retryTranslation(Request $request, string $sourceKey): JsonResponse
    {
        $validated = $request->validate([
            'limit' => 'nullable|integer|min:1|max:100',
            'run_id' => 'nullable|integer|min:1|exists:bot_runs,id',
        ]);

        $normalizedSourceKey = strtolower(trim($sourceKey));
        $source = BotSource::query()->where('key', $normalizedSourceKey)->first();
        if (!$source) {
            return response()->json([
                'message' => sprintf('Bot source "%s" was not found.', $normalizedSourceKey),
            ], 404);
        }

        $limit = (int) ($validated['limit'] ?? (int) $request->query('limit', 10));
        if ($limit <= 0) {
            $limit = 10;
        }
        $runId = isset($validated['run_id']) ? (int) $validated['run_id'] : null;

        $query = BotItem::query()
            ->where('source_id', $source->id)
            ->whereIn('translation_status', [
                BotTranslationStatus::FAILED->value,
                BotTranslationStatus::PENDING->value,
            ])
            ->orderByDesc('fetched_at')
            ->orderByDesc('id');

        if ($runId !== null) {
            $query->where(function (Builder $builder) use ($runId): void {
                $builder
                    ->where('run_id', $runId)
                    ->orWhere('meta->last_seen_run_id', $runId);
            });
        }

        $items = $query->limit($limit)->get();

        $doneCount = 0;
        $skippedCount = 0;
        $failedCount = 0;
        $updatedItemIds = [];

        foreach ($items as $item) {
            $meta = is_array($item->meta) ? $item->meta : [];
            $title = trim((string) $item->title);
            $content = trim((string) ($item->content ?: $item->summary ?: ''));

            if ($title === '' && $content === '') {
                $meta['translation'] = array_replace(
                    is_array($meta['translation'] ?? null) ? $meta['translation'] : [],
                    [
                        'provider' => 'none',
                        'reason' => 'empty_input',
                        'target_lang' => 'sk',
                        'error' => null,
                        'translated_at' => now()->toIso8601String(),
                    ]
                );
                unset($meta['translation_error']);
                $item->forceFill([
                    'translation_status' => BotTranslationStatus::SKIPPED->value,
                    'translation_error' => null,
                    'translation_provider' => 'none',
                    'translated_at' => now(),
                    'meta' => $meta,
                ])->save();

                $skippedCount++;
                $updatedItemIds[] = $item->id;
                continue;
            }

            try {
                $result = $this->translationService->translate($title, $content, 'sk');
                $resultMeta = is_array($result['meta'] ?? null) ? $result['meta'] : [];
                $status = strtolower(trim((string) ($result['status'] ?? BotTranslationStatus::DONE->value)));
                if (!in_array($status, [
                    BotTranslationStatus::DONE->value,
                    BotTranslationStatus::SKIPPED->value,
                    BotTranslationStatus::FAILED->value,
                ], true)) {
                    $status = BotTranslationStatus::DONE->value;
                }

                $meta['translation'] = $resultMeta;
                unset($meta['translation_error']);
                $translationProvider = $this->nullableString(data_get($resultMeta, 'provider'));

                $item->forceFill([
                    'title_translated' => $this->nullableString($result['translated_title'] ?? $result['title_translated'] ?? null),
                    'content_translated' => $this->nullableString($result['translated_content'] ?? $result['content_translated'] ?? null),
                    'translation_status' => $status,
                    'translation_error' => $this->nullableString(data_get($resultMeta, 'error')),
                    'translation_provider' => $translationProvider,
                    'translated_at' => now(),
                    'meta' => $meta,
                ])->save();

                if ($status === BotTranslationStatus::DONE->value) {
                    $doneCount++;
                } elseif ($status === BotTranslationStatus::SKIPPED->value) {
                    $skippedCount++;
                } else {
                    $failedCount++;
                }
                $updatedItemIds[] = $item->id;
            } catch (Throwable $e) {
                $failedCount++;
                $errorMessage = $this->truncateErrorText($e->getMessage(), 300);
                $meta['translation_error'] = $errorMessage;
                Log::warning('Admin bot translation retry failed.', [
                    'source_key' => $normalizedSourceKey,
                    'stable_key' => (string) $item->stable_key,
                    'error' => $errorMessage,
                ]);

                $item->forceFill([
                    'translation_status' => BotTranslationStatus::FAILED->value,
                    'translation_error' => $errorMessage,
                    'translation_provider' => null,
                    'translated_at' => now(),
                    'meta' => $meta,
                ])->save();
            }
        }

        return response()->json([
            'source_key' => $normalizedSourceKey,
            'run_id' => $runId,
            'limit' => $limit,
            'retried_count' => $items->count(),
            'done_count' => $doneCount,
            'skipped_count' => $skippedCount,
            'failed_count' => $failedCount,
            'updated_item_ids' => $updatedItemIds,
        ]);
    }

    public function backfillTranslation(Request $request, string $sourceKey): JsonResponse
    {
        $validated = $request->validate([
            'limit' => 'nullable|integer|min:1|max:100',
            'run_id' => 'nullable|integer|min:1|exists:bot_runs,id',
        ]);

        $normalizedSourceKey = strtolower(trim($sourceKey));
        $source = BotSource::query()->where('key', $normalizedSourceKey)->first();
        if (!$source) {
            return response()->json([
                'message' => sprintf('Bot source "%s" was not found.', $normalizedSourceKey),
            ], 404);
        }

        $limit = (int) ($validated['limit'] ?? (int) $request->query('limit', 10));
        if ($limit <= 0) {
            $limit = 10;
        }
        $runId = isset($validated['run_id']) ? (int) $validated['run_id'] : null;

        try {
            $result = $this->backfillService->backfill($source, $limit, $runId);
        } catch (Throwable $e) {
            Log::warning('Admin bot translation backfill failed.', [
                'source_key' => $normalizedSourceKey,
                'run_id' => $runId,
                'error' => $this->truncateErrorText($e->getMessage(), 240),
            ]);

            return response()->json([
                'source_key' => $normalizedSourceKey,
                'run_id' => $runId,
                'limit' => $limit,
                'scanned' => 0,
                'updated_posts' => 0,
                'skipped' => 0,
                'failed' => 1,
                'failures' => [[
                    'post_id' => null,
                    'reason' => 'backfill_failed',
                ]],
            ], 422);
        }

        return response()->json($result);
    }

    public function publishItem(Request $request, int $botItemId): JsonResponse
    {
        $request->validate([
            'force' => 'sometimes|boolean',
        ]);

        $item = BotItem::query()->find($botItemId);
        if (!$item) {
            return response()->json([
                'message' => 'Polozka bota sa nenasla.',
            ], 404);
        }

        $publishStatus = strtolower(trim((string) ($item->publish_status?->value ?? $item->publish_status)));
        if ($item->post_id || $publishStatus === BotPublishStatus::PUBLISHED->value) {
            return response()->json([
                'message' => 'Polozka je uz publikovana.',
                'already_published' => true,
                'item' => $this->serializeItem($item->fresh() ?? $item),
            ]);
        }

        if ($publishStatus === BotPublishStatus::SKIPPED->value) {
            $skipReason = $this->nullableString(data_get($item->meta, 'skip_reason'));

            return response()->json([
                'message' => 'Polozka je preskocena a neda sa publikovat.',
                'skip_reason' => $skipReason,
            ], 422);
        }

        $result = $this->publisherService->publishItemToAstroFeed($item, 'admin');
        $item = $item->fresh() ?? $item;

        if ($result->isPublished() || $item->post_id || $this->isPublishedStatus($item)) {
            $item = $this->markItemPublishedManually($item);

            return response()->json([
                'message' => 'Polozka publikovana.',
                'already_published' => false,
                'item' => $this->serializeItem($item),
            ]);
        }

        $skipReason = $result->reason ?? $this->nullableString(data_get($item->meta, 'skip_reason'));

        return response()->json([
            'message' => 'Polozku sa nepodarilo publikovat.',
            'skip_reason' => $skipReason,
            'item' => $this->serializeItem($item),
        ], 422);
    }

    public function publishRun(Request $request, int $runId): JsonResponse
    {
        $validated = $request->validate([
            'publish_limit' => 'nullable|integer|min:1|max:100',
        ]);

        $run = BotRun::query()->find($runId);
        if (!$run) {
            return response()->json([
                'message' => 'Beh sa nenasiel.',
            ], 404);
        }

        $limit = isset($validated['publish_limit']) ? (int) $validated['publish_limit'] : 10;
        $items = $this->runLinkedItemsQuery($run)
            ->whereNull('post_id')
            ->where('publish_status', BotPublishStatus::PENDING->value)
            ->orderBy('fetched_at')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $publishedItemIds = [];
        $skippedCount = 0;
        $failedCount = 0;

        foreach ($items as $item) {
            try {
                $result = $this->publisherService->publishItemToAstroFeed($item, 'admin');
                $item = $item->fresh() ?? $item;

                if ($result->isPublished() || $item->post_id || $this->isPublishedStatus($item)) {
                    $item = $this->markItemPublishedManually($item);
                    $publishedItemIds[] = $item->id;
                    continue;
                }

                $skippedCount++;
            } catch (\Throwable) {
                $failedCount++;
            }
        }

        return response()->json([
            'run_id' => $run->id,
            'publish_limit' => $limit,
            'attempted_count' => $items->count(),
            'published_count' => count($publishedItemIds),
            'skipped_count' => $skippedCount,
            'failed_count' => $failedCount,
            'published_item_ids' => $publishedItemIds,
        ]);
    }

    public function deleteItemPost(int $botItemId): JsonResponse
    {
        $item = BotItem::query()->find($botItemId);
        if (!$item) {
            return response()->json([
                'message' => 'Polozka bota sa nenasla.',
            ], 404);
        }

        $postId = (int) ($item->post_id ?? 0);
        if ($postId <= 0) {
            return response()->json([
                'message' => 'Polozka nema publikovany prispevok na vymazanie.',
            ], 422);
        }

        $post = Post::query()->find($postId);
        if ($post) {
            $this->postService->deletePost($post);
        }

        $item = $this->markItemPostVymazaneManually($item, $postId);

        return response()->json([
            'message' => 'Publikovany prispevok bol vymazany.',
            'item' => $this->serializeItem($item),
            'deleted_post_id' => $postId,
        ]);
    }

    public function deleteAllPosts(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'source_key' => 'nullable|string|max:120',
            'bot_identity' => 'nullable|string|in:kozmo,stela',
        ]);

        $sourceKey = strtolower(trim((string) ($validated['source_key'] ?? '')));
        $botIdentity = strtolower(trim((string) ($validated['bot_identity'] ?? '')));

        $query = BotItem::query()
            ->whereNotNull('post_id')
            ->where('post_id', '>', 0);

        if ($sourceKey !== '') {
            $source = BotSource::query()->where('key', $sourceKey)->first();
            if (!$source) {
                return response()->json([
                    'message' => sprintf('Bot source "%s" was not found.', $sourceKey),
                ], 404);
            }

            $query->where('source_id', (int) $source->id);
        }

        if ($botIdentity !== '') {
            $query->where('bot_identity', $botIdentity);
        }

        $matchedCount = (clone $query)->count();
        if ($matchedCount <= 0) {
            return response()->json([
                'message' => 'Pre vybrane filtre sa nenasli publikovane bot prispevky.',
                'matched_items' => 0,
                'deleted_posts' => 0,
                'missing_posts' => 0,
                'updated_items' => 0,
                'failed_items' => 0,
                'sample_deleted_post_ids' => [],
            ]);
        }

        $deletedPosts = 0;
        $missingPosts = 0;
        $updatedItems = 0;
        $failedItems = 0;
        $sampleVymazanePostIds = [];

        $query
            ->orderBy('id')
            ->chunkById(100, function ($items) use (
                &$deletedPosts,
                &$missingPosts,
                &$updatedItems,
                &$failedItems,
                &$sampleVymazanePostIds
            ): void {
                foreach ($items as $item) {
                    $postId = (int) ($item->post_id ?? 0);
                    if ($postId <= 0) {
                        continue;
                    }

                    try {
                        $post = Post::query()->find($postId);
                        if ($post) {
                            $this->postService->deletePost($post);
                            $deletedPosts++;
                            if (count($sampleVymazanePostIds) < 50) {
                                $sampleVymazanePostIds[] = $postId;
                            }
                        } else {
                            $missingPosts++;
                        }

                        $this->markItemPostVymazaneManually($item, $postId);
                        $updatedItems++;
                    } catch (Throwable $e) {
                        $failedItems++;
                        Log::warning('Admin failed to delete published bot post in bulk.', [
                            'bot_item_id' => $item->id,
                            'post_id' => $postId,
                            'error' => $this->truncateErrorText($e->getMessage(), 240),
                        ]);
                    }
                }
            }, 'id');

        return response()->json([
            'message' => 'Hromadne mazanie dokoncene.',
            'matched_items' => $matchedCount,
            'deleted_posts' => $deletedPosts,
            'missing_posts' => $missingPosts,
            'updated_items' => $updatedItems,
            'failed_items' => $failedItems,
            'sample_deleted_post_ids' => $sampleVymazanePostIds,
        ]);
    }

    public function postRetention(): JsonResponse
    {
        return response()->json([
            'data' => $this->botPostRetentionService->settingsPayload(),
        ]);
    }

    public function updatePostRetention(Request $request): JsonResponse
    {
        $allowedHours = $this->botPostRetentionService->settingsPayload()['allowed_hours'] ?? [];
        $validated = $request->validate([
            'enabled' => 'sometimes|boolean',
            'auto_delete_after_hours' => ['sometimes', 'integer', Rule::in($allowedHours)],
        ]);

        $updated = $this->botPostRetentionService->updateSettings(
            array_key_exists('enabled', $validated)
                ? (bool) $validated['enabled']
                : null,
            array_key_exists('auto_delete_after_hours', $validated)
                ? (int) $validated['auto_delete_after_hours']
                : null,
        );

        return response()->json([
            'data' => $updated,
        ]);
    }

    public function runPostRetentionCleanup(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'limit' => 'nullable|integer|min:1|max:1000',
        ]);

        $result = $this->botPostRetentionService->cleanupExpiredPosts(
            isset($validated['limit']) ? (int) $validated['limit'] : 200
        );

        return response()->json([
            'message' => 'Cistenie retention bot prispevkov dokoncene.',
            'data' => $result,
        ]);
    }

}

