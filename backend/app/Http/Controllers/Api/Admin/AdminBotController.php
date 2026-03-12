<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\BotRunFailureReason;
use App\Http\Controllers\Api\Admin\Concerns\ManagesBotPublishing;
use App\Http\Controllers\Api\Admin\Concerns\ManagesBotSources;
use App\Http\Controllers\Api\Admin\Concerns\ManagesBotTranslations;
use App\Http\Controllers\Api\Admin\Concerns\SerializesBotAdminData;
use App\Http\Controllers\Controller;
use App\Models\BotActivityLog;
use App\Models\BotItem;
use App\Models\BotRun;
use App\Models\BotSchedule;
use App\Models\BotSource;
use App\Models\User;
use App\Services\Bots\BotOverviewService;
use App\Services\Bots\BotPostRetentionService;
use App\Services\Bots\BotPostTranslationBackfillService;
use App\Services\Bots\BotPublisherService;
use App\Services\Bots\BotRunner;
use App\Services\Bots\BotSourceHealthPolicy;
use App\Services\Bots\BotSourceHealthService;
use App\Services\Bots\BotSourceSyncService;
use App\Services\Bots\Contracts\BotTranslationServiceInterface;
use App\Services\PostService;
use App\Services\Translation\TranslationOutageSimulationService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class AdminBotController extends Controller
{
    use SerializesBotAdminData;
    use ManagesBotSources;
    use ManagesBotTranslations;
    use ManagesBotPublishing;

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
}
