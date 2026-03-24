<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessEventCandidatePublishRunJob;
use App\Jobs\TranslateEventCandidateJob;
use App\Models\CrawlRun;
use App\Models\Event;
use App\Models\EventCandidate;
use App\Models\EventCandidatePublishRun;
use App\Services\Events\EventCandidateBatchPublisher;
use App\Services\Events\EventCandidatePublisher;
use App\Services\Events\EventDescriptionOriginRecorder;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class EventCandidateReviewController extends Controller
{
    public function __construct(
        private readonly EventCandidatePublisher $publisher,
        private readonly EventCandidateBatchPublisher $batchPublisher,
        private readonly EventDescriptionOriginRecorder $originRecorder,
    ) {
    }

    public function approve(Request $request, EventCandidate $candidate)
    {
        if ($candidate->status !== EventCandidate::STATUS_PENDING) {
            return response()->json([
                'ok' => false,
                'message' => 'Kandidat nie je v stave cakania.',
                'status' => $candidate->status,
            ], 409);
        }

        $validated = $request->validate([
            'mode' => ['nullable', 'string', 'in:template,ai,mix'],
        ]);
        $publishGenerationMode = $this->normalizePublishGenerationMode($validated['mode'] ?? null);
        $effectiveGenerationMode = $this->resolveCandidatePublishGenerationMode($candidate, $publishGenerationMode);
        $this->archiveCandidateDescriptionVariant(
            $candidate,
            'approve_single_before_mode_switch',
            $publishGenerationMode
        );
        $this->runSynchronousRetranslationForPublish((int) $candidate->id, $effectiveGenerationMode);
        $candidate->refresh();

        $event = $this->publisher->approve($candidate, (int) $request->user()->id);

        return response()->json([
            'ok' => true,
            'candidate' => $candidate->fresh(),
            'published_event_id' => $event->id,
            'publish_generation_mode' => $publishGenerationMode,
        ]);
    }

    public function approveBatch(Request $request): JsonResponse
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }

        $validated = $this->validateApproveBatchPayload($request);
        $publishGenerationMode = $this->normalizePublishGenerationMode($validated['mode'] ?? null);
        $limit = (int) ($validated['limit'] ?? 1000);

        $ids = $this->resolveApproveBatchCandidateIds($validated);
        if ($ids === []) {
            return response()->json([
                'ok' => true,
                'published' => 0,
                'failed' => 0,
                'total_selected' => 0,
                'limit_applied' => $limit,
                'publish_generation_mode' => $publishGenerationMode,
            ]);
        }

        $published = 0;
        $failed = 0;
        $reviewerId = (int) $request->user()->id;

        foreach ($ids as $candidateId) {
            try {
                $result = $this->batchPublisher->approvePendingCandidate(
                    candidateId: $candidateId,
                    reviewerUserId: $reviewerId,
                    publishGenerationMode: $publishGenerationMode
                );

                if ($result) {
                    $published++;
                } else {
                    $failed++;
                }
            } catch (\Throwable $exception) {
                $failed++;
                Log::warning('Event candidate batch approve failed', [
                    'candidate_id' => $candidateId,
                    'publish_mode' => $publishGenerationMode,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        return response()->json([
            'ok' => true,
            'published' => $published,
            'failed' => $failed,
            'total_selected' => count($ids),
            'limit_applied' => $limit,
            'publish_generation_mode' => $publishGenerationMode,
        ]);
    }

    public function approveBatchStart(Request $request): JsonResponse
    {
        $validated = $this->validateApproveBatchPayload($request);
        $publishGenerationMode = $this->normalizePublishGenerationMode($validated['mode'] ?? null);
        $limit = (int) ($validated['limit'] ?? 1000);
        $candidateIds = $this->resolveApproveBatchCandidateIds($validated);

        $run = EventCandidatePublishRun::query()->create([
            'status' => $candidateIds === []
                ? EventCandidatePublishRun::STATUS_COMPLETED
                : EventCandidatePublishRun::STATUS_QUEUED,
            'reviewer_user_id' => (int) $request->user()->id,
            'publish_generation_mode' => $publishGenerationMode,
            'total_selected' => count($candidateIds),
            'processed' => 0,
            'published' => 0,
            'failed' => 0,
            'limit_applied' => $limit,
            'started_at' => $candidateIds === [] ? now() : null,
            'finished_at' => $candidateIds === [] ? now() : null,
            'filters' => $this->normalizeApproveBatchFiltersForStorage($validated),
            'meta' => [
                'candidate_ids' => $candidateIds,
                'trigger' => 'admin_event_candidates_approve_batch',
            ],
            'error_message' => null,
        ]);

        if ($candidateIds !== []) {
            $this->dispatchBatchPublishRun((int) $run->id);
        }

        $statusCode = $candidateIds === [] ? 200 : 202;

        return response()->json([
            'ok' => true,
            'status' => $candidateIds === [] ? 'done' : 'accepted',
            'run' => $this->serializePublishRun($run->fresh() ?? $run),
        ], $statusCode);
    }

    public function approveBatchRunStatus(EventCandidatePublishRun $run): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'run' => $this->serializePublishRun($run),
        ]);
    }

    public function reject(Request $request, EventCandidate $candidate)
    {
        if ($candidate->status !== EventCandidate::STATUS_PENDING) {
            return response()->json([
                'ok' => false,
                'message' => 'Kandidat nie je v stave cakania.',
                'status' => $candidate->status,
            ], 409);
        }

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $this->publisher->reject(
                $candidate,
                (int) $request->user()->id,
                (string) ($validated['reason'] ?? '')
            );
        } catch (RuntimeException) {
            return response()->json([
                'ok' => false,
                'message' => 'Kandidat nie je v stave cakania.',
                'status' => $candidate->fresh()->status,
            ], 409);
        }

        return response()->json([
            'ok' => true,
            'candidate' => $candidate->fresh(),
        ]);
    }

    public function retranslate(Request $request, EventCandidate $candidate)
    {
        $validated = $request->validate([
            'mode' => ['nullable', 'string', 'in:ai,template'],
        ]);
        $requestedMode = $this->normalizeRetranslationMode($validated['mode'] ?? null);

        $candidate->update([
            'translation_status' => EventCandidate::TRANSLATION_PENDING,
            'translation_mode' => null,
            'translation_error' => null,
        ]);

        try {
            $this->dispatchRetranslationJob((int) $candidate->id, $requestedMode);
        } catch (\Throwable $exception) {
            Log::warning('Event candidate retranslation dispatch failed', [
                'candidate_id' => (int) $candidate->id,
                'mode' => $requestedMode,
                'error' => $exception->getMessage(),
            ]);
        }

        return response()->json([
            'ok' => true,
            'candidate' => $candidate->fresh(),
            'mode_applied' => $requestedMode,
            'message' => 'Generovanie popisu bolo zaradene do fronty.',
        ]);
    }

    public function retranslateBatch(Request $request)
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }

        $validated = $request->validate([
            'ids'         => ['nullable', 'array', 'max:500'],
            'ids.*'       => ['integer', 'min:1'],
            'status'      => ['nullable', 'string', 'max:50'],
            'type'        => ['nullable', 'string', 'max:100'],
            'raw_type'    => ['nullable', 'string', 'max:100'],
            'description_mode' => ['nullable', 'string', 'in:all,missing,template,ai,ai_refined,translated,manual'],
            'source_name' => ['nullable', 'string', 'max:100'],
            'source'      => ['nullable', 'string', 'max:100'],
            'source_key'  => ['nullable', 'string', 'max:100'],
            'run_id'      => ['nullable', 'integer', 'min:1'],
            'year'        => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'month'       => ['nullable', 'integer', 'min:1', 'max:12'],
            'week'        => ['nullable', 'integer', 'min:1', 'max:53'],
            'date_from'   => ['nullable', 'date'],
            'date_to'     => ['nullable', 'date', 'after_or_equal:date_from'],
            'q'           => ['nullable', 'string', 'max:200'],
            'limit'       => ['nullable', 'integer', 'min:1', 'max:5000'],
            'mode'        => ['nullable', 'string', 'in:ai,template,mix'],
            'ai_scope'    => ['nullable', 'string', 'in:all,missing,template'],
        ]);

        $explicitIds = isset($validated['ids']) ? array_map('intval', (array) $validated['ids']) : null;

        $status = $validated['status'] ?? EventCandidate::STATUS_PENDING;
        $type = $validated['type'] ?? null;
        $rawType = $validated['raw_type'] ?? null;
        $descriptionMode = $validated['description_mode'] ?? null;
        $sourceName = $validated['source_name'] ?? $validated['source'] ?? null;
        $sourceKey = $validated['source_key'] ?? null;
        $runId = $validated['run_id'] ?? null;
        $year = $validated['year'] ?? null;
        $month = $validated['month'] ?? null;
        $week = $validated['week'] ?? null;
        $dateFrom = isset($validated['date_from']) ? (string) $validated['date_from'] : null;
        $dateTo = isset($validated['date_to']) ? (string) $validated['date_to'] : null;
        $q = isset($validated['q']) ? trim((string) $validated['q']) : null;
        $limit = (int) ($validated['limit'] ?? 1000);
        $rawMode = strtolower(trim((string) ($validated['mode'] ?? '')));
        $isMixMode = $rawMode === 'mix';
        $requestedMode = $this->normalizeRetranslationMode($validated['mode'] ?? null);
        $aiScope = $this->normalizeAiScope($validated['ai_scope'] ?? null);

        $query = EventCandidate::query()
            ->when($status, fn ($qq) => $qq->where('status', $status))
            ->when($type, fn ($qq) => $qq->where('type', $type))
            ->when($rawType, fn ($qq) => $qq->where('raw_type', $rawType))
            ->when($sourceName, fn ($qq) => $qq->where('source_name', $sourceName))
            ->when($sourceKey, function ($qq) use ($sourceKey) {
                $qq->whereHas('eventSource', fn ($q) => $q->where('key', $sourceKey));
            })
            ->when($runId, function ($qq) use ($runId) {
                $run = CrawlRun::query()->find((int) $runId);
                if (! $run) {
                    $qq->whereRaw('1 = 0');
                    return;
                }

                if ($run->event_source_id !== null) {
                    $qq->where('event_source_id', (int) $run->event_source_id);
                } else {
                    $qq->where('source_name', (string) $run->source_name);
                }

                $startedAt = $run->started_at ? CarbonImmutable::instance($run->started_at) : null;
                $finishedAt = $run->finished_at ? CarbonImmutable::instance($run->finished_at) : null;

                if ($startedAt !== null) {
                    $windowEnd = $finishedAt;
                    if ($windowEnd === null || $windowEnd->lessThan($startedAt)) {
                        $windowEnd = $startedAt->addMinutes(30);
                    } else {
                        $windowEnd = $windowEnd->addMinutes(5);
                    }

                    $qq->whereBetween('created_at', [$startedAt, $windowEnd]);
                }
            });

        $this->applyCalendarFilter($query, $year, $month, $week, $dateFrom, $dateTo);
        $this->applyDescriptionModeFilter($query, $descriptionMode);

        $query->when($q !== null && $q !== '', function ($qq) use ($q) {
            $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%';
            $qq->where(function ($sub) use ($like) {
                $sub->where('title', 'like', $like)
                    ->orWhere('short', 'like', $like)
                    ->orWhere('description', 'like', $like);
            });
        });
        if ($explicitIds !== null) {
            $candidates = EventCandidate::query()
                ->whereIn('id', $explicitIds)
                ->get(['id', 'title', 'translated_title', 'type', 'source_name']);
        } else {
            $this->applyAiScopeFilter($query, $aiScope);
            $candidates = $query->orderByDesc('id')->limit($limit)
                ->get(['id', 'title', 'translated_title', 'type', 'source_name']);
        }
        $ids = $candidates->map(fn ($c) => (int) $c->id)->all();

        if ($ids === []) {
            return response()->json([
                'ok' => true,
                'queued' => 0,
                'failed' => 0,
                'total_selected' => 0,
                'items' => [],
            ]);
        }

        $itemsPreview = $candidates->map(fn ($c) => [
            'id' => (int) $c->id,
            'title' => (string) ($c->translated_title ?: $c->title ?: ''),
            'type' => (string) ($c->type ?? ''),
            'source' => (string) ($c->source_name ?? ''),
        ])->values()->all();

        // In mix mode snapshot each candidate's current translation_mode before clearing.
        $perCandidateMode = [];
        if ($isMixMode) {
            $snapshot = EventCandidate::query()
                ->whereIn('id', $ids)
                ->pluck('translation_mode', 'id');
            foreach ($ids as $cid) {
                $perCandidateMode[$cid] = (string) ($snapshot[$cid] ?? '') === EventCandidate::TRANSLATION_MODE_TEMPLATE
                    ? 'template'
                    : 'ai';
            }
        }

        EventCandidate::query()
            ->whereIn('id', $ids)
            ->update([
                'translation_status' => EventCandidate::TRANSLATION_PENDING,
                'translation_mode' => null,
                'translation_error' => null,
            ]);

        $queued = 0;
        $failed = 0;

        foreach ($ids as $candidateId) {
            try {
                $dispatchMode = $isMixMode ? ($perCandidateMode[$candidateId] ?? 'ai') : $requestedMode;
                $this->dispatchBatchRetranslationJob($candidateId, $dispatchMode);
                $queued++;
            } catch (\Throwable $exception) {
                $failed++;
                Log::warning('Event candidate batch retranslation dispatch failed', [
                    'candidate_id' => $candidateId,
                    'mode' => $isMixMode ? ($perCandidateMode[$candidateId] ?? 'ai') : $requestedMode,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        return response()->json([
            'ok' => true,
            'queued' => $queued,
            'failed' => $failed,
            'total_selected' => count($ids),
            'limit_applied' => $limit,
            'mode_applied' => $isMixMode ? 'mix' : $requestedMode,
            'scope_applied' => $aiScope,
            'items' => $itemsPreview,
        ]);
    }

    public function updateTranslation(Request $request, EventCandidate $candidate)
    {
        $validated = $request->validate([
            'translated_title' => ['required', 'string', 'max:500'],
            'translated_description' => ['nullable', 'string', 'max:20000'],
        ]);

        $translatedTitle = trim((string) $validated['translated_title']);
        $translatedDescription = array_key_exists('translated_description', $validated)
            ? trim((string) ($validated['translated_description'] ?? ''))
            : null;

        if ($translatedTitle === '') {
            return response()->json([
                'ok' => false,
                'message' => 'Preložený nadpis je povinný.',
            ], 422);
        }

        $translatedDescription = $translatedDescription !== null && $translatedDescription !== ''
            ? $translatedDescription
            : null;

        $short = $translatedDescription !== null
            ? mb_substr($translatedDescription, 0, 180)
            : mb_substr($translatedTitle, 0, 180);

        $this->archiveCandidateDescriptionVariant(
            $candidate,
            'manual_edit_before_override',
            'manual'
        );

        $candidate->update([
            'translated_title' => $translatedTitle,
            'translated_description' => $translatedDescription,
            'short' => $short,
            'description' => $translatedDescription,
            'translation_status' => EventCandidate::TRANSLATION_DONE,
            'translation_mode' => EventCandidate::TRANSLATION_MODE_MANUAL,
            'translation_error' => null,
            'translated_at' => now(),
        ]);

        if ($candidate->published_event_id) {
            $event = Event::query()->find((int) $candidate->published_event_id);
            if ($event !== null) {
                $event->update([
                    'title' => $translatedTitle,
                    'description' => $translatedDescription,
                    'short' => $short,
                ]);

                $freshEvent = $event->fresh();
                if ($freshEvent instanceof Event) {
                    $this->originRecorder->record(
                        event: $freshEvent,
                        source: 'candidate_translation_manual_edit',
                        sourceDetail: 'admin_translation_patch',
                        candidateId: (int) $candidate->id
                    );
                }
            }
        }

        return response()->json([
            'ok' => true,
            'candidate' => $candidate->fresh(),
        ]);
    }

    /**
     * @return array<string,mixed>
     */
    private function validateApproveBatchPayload(Request $request): array
    {
        return $request->validate([
            'status'      => ['nullable', 'string', 'max:50'],
            'type'        => ['nullable', 'string', 'max:100'],
            'raw_type'    => ['nullable', 'string', 'max:100'],
            'description_mode' => ['nullable', 'string', 'in:all,missing,template,ai,ai_refined,translated,manual'],
            'source_name' => ['nullable', 'string', 'max:100'],
            'source'      => ['nullable', 'string', 'max:100'],
            'source_key'  => ['nullable', 'string', 'max:100'],
            'run_id'      => ['nullable', 'integer', 'min:1'],
            'year'        => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'month'       => ['nullable', 'integer', 'min:1', 'max:12'],
            'week'        => ['nullable', 'integer', 'min:1', 'max:53'],
            'date_from'   => ['nullable', 'date'],
            'date_to'     => ['nullable', 'date', 'after_or_equal:date_from'],
            'q'           => ['nullable', 'string', 'max:200'],
            'limit'       => ['nullable', 'integer', 'min:1', 'max:5000'],
            'mode'        => ['nullable', 'string', 'in:template,ai,mix'],
        ]);
    }

    /**
     * @param  array<string,mixed>  $validated
     * @return array<int,int>
     */
    private function resolveApproveBatchCandidateIds(array $validated): array
    {
        $limit = (int) ($validated['limit'] ?? 1000);

        return $this->buildApproveBatchQuery($validated)
            ->where('status', EventCandidate::STATUS_PENDING)
            ->orderByDesc('id')
            ->limit($limit)
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();
    }

    /**
     * @param  array<string,mixed>  $validated
     */
    private function buildApproveBatchQuery(array $validated): Builder
    {
        $status = $validated['status'] ?? EventCandidate::STATUS_PENDING;
        $type = $validated['type'] ?? null;
        $rawType = $validated['raw_type'] ?? null;
        $descriptionMode = $validated['description_mode'] ?? null;
        $sourceName = $validated['source_name'] ?? $validated['source'] ?? null;
        $sourceKey = $validated['source_key'] ?? null;
        $runId = $validated['run_id'] ?? null;
        $year = $validated['year'] ?? null;
        $month = $validated['month'] ?? null;
        $week = $validated['week'] ?? null;
        $dateFrom = isset($validated['date_from']) ? (string) $validated['date_from'] : null;
        $dateTo = isset($validated['date_to']) ? (string) $validated['date_to'] : null;
        $q = isset($validated['q']) ? trim((string) $validated['q']) : null;

        $query = EventCandidate::query()
            ->when($status, fn ($qq) => $qq->where('status', $status))
            ->when($type, fn ($qq) => $qq->where('type', $type))
            ->when($rawType, fn ($qq) => $qq->where('raw_type', $rawType))
            ->when($sourceName, fn ($qq) => $qq->where('source_name', $sourceName))
            ->when($sourceKey, function ($qq) use ($sourceKey): void {
                $qq->whereHas('eventSource', fn ($query) => $query->where('key', $sourceKey));
            })
            ->when($runId, function ($qq) use ($runId): void {
                $run = CrawlRun::query()->find((int) $runId);
                if (! $run) {
                    $qq->whereRaw('1 = 0');
                    return;
                }

                if ($run->event_source_id !== null) {
                    $qq->where('event_source_id', (int) $run->event_source_id);
                } else {
                    $qq->where('source_name', (string) $run->source_name);
                }

                $startedAt = $run->started_at ? CarbonImmutable::instance($run->started_at) : null;
                $finishedAt = $run->finished_at ? CarbonImmutable::instance($run->finished_at) : null;

                if ($startedAt !== null) {
                    $windowEnd = $finishedAt;
                    if ($windowEnd === null || $windowEnd->lessThan($startedAt)) {
                        $windowEnd = $startedAt->addMinutes(30);
                    } else {
                        $windowEnd = $windowEnd->addMinutes(5);
                    }

                    $qq->whereBetween('created_at', [$startedAt, $windowEnd]);
                }
            });

        $this->applyCalendarFilter($query, $year, $month, $week, $dateFrom, $dateTo);
        $this->applyDescriptionModeFilter($query, $descriptionMode);

        $query->when($q !== null && $q !== '', function ($qq) use ($q): void {
            $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%';
            $qq->where(function ($sub) use ($like): void {
                $sub->where('title', 'like', $like)
                    ->orWhere('short', 'like', $like)
                    ->orWhere('description', 'like', $like);
            });
        });

        return $query;
    }

    /**
     * @param  array<string,mixed>  $validated
     * @return array<string,mixed>
     */
    private function normalizeApproveBatchFiltersForStorage(array $validated): array
    {
        $keys = [
            'status',
            'type',
            'raw_type',
            'description_mode',
            'source_name',
            'source',
            'source_key',
            'run_id',
            'year',
            'month',
            'week',
            'date_from',
            'date_to',
            'q',
            'limit',
            'mode',
        ];

        $stored = [];
        foreach ($keys as $key) {
            if (! array_key_exists($key, $validated)) {
                continue;
            }

            $value = $validated[$key];
            if (is_string($value)) {
                $value = trim($value);
            }

            if ($value === '' || $value === null) {
                continue;
            }

            $stored[$key] = $value;
        }

        return $stored;
    }

    private function dispatchBatchPublishRun(int $runId): void
    {
        $queueConnection = strtolower(trim((string) config('queue.default', 'sync')));

        if ($queueConnection === 'sync') {
            ProcessEventCandidatePublishRunJob::dispatchAfterResponse($runId);
            return;
        }

        ProcessEventCandidatePublishRunJob::dispatch($runId)->afterCommit();
    }

    /**
     * @return array<string,mixed>
     */
    private function serializePublishRun(EventCandidatePublishRun $run): array
    {
        $totalSelected = max(0, (int) $run->total_selected);
        $processed = max(0, (int) $run->processed);

        if ($totalSelected > 0) {
            $processed = min($processed, $totalSelected);
        }

        $progressPercent = $totalSelected === 0
            ? ($run->isTerminal() ? 100 : 0)
            : (int) round(($processed / $totalSelected) * 100);
        $progressPercent = max(0, min(100, $progressPercent));

        return [
            'id' => (int) $run->id,
            'status' => (string) $run->status,
            'is_terminal' => $run->isTerminal(),
            'publish_generation_mode' => (string) $run->publish_generation_mode,
            'total_selected' => $totalSelected,
            'processed' => $processed,
            'published' => max(0, (int) $run->published),
            'failed' => max(0, (int) $run->failed),
            'limit_applied' => $run->limit_applied !== null ? (int) $run->limit_applied : null,
            'progress_percent' => $progressPercent,
            'started_at' => $run->started_at?->toIso8601String(),
            'finished_at' => $run->finished_at?->toIso8601String(),
            'error_message' => $run->error_message !== null ? (string) $run->error_message : null,
            'created_at' => $run->created_at?->toIso8601String(),
            'updated_at' => $run->updated_at?->toIso8601String(),
        ];
    }

    private function applyCalendarFilter(
        Builder $query,
        mixed $year,
        mixed $month,
        mixed $week,
        ?string $dateFrom,
        ?string $dateTo,
    ): void {
        $hasDateRange = ($dateFrom !== null && trim($dateFrom) !== '') || ($dateTo !== null && trim($dateTo) !== '');

        if ($hasDateRange) {
            $from = $dateFrom !== null && trim($dateFrom) !== ''
                ? CarbonImmutable::parse($dateFrom)->startOfDay()
                : null;
            $to = $dateTo !== null && trim($dateTo) !== ''
                ? CarbonImmutable::parse($dateTo)->endOfDay()
                : null;

            if ($from && $to) {
                $query->whereBetween('start_at', [$from, $to]);
                return;
            }

            if ($from) {
                $query->where('start_at', '>=', $from);
                return;
            }

            if ($to) {
                $query->where('start_at', '<=', $to);
            }

            return;
        }

        if ($year && $month) {
            $start = CarbonImmutable::create((int) $year, (int) $month, 1, 0, 0, 0);
            $query->whereBetween('start_at', [$start->startOfDay(), $start->endOfMonth()->endOfDay()]);
            return;
        }

        if ($year && $week) {
            $start = CarbonImmutable::now()->setISODate((int) $year, (int) $week, 1)->startOfDay();
            $end = CarbonImmutable::now()->setISODate((int) $year, (int) $week, 7)->endOfDay();
            $query->whereBetween('start_at', [$start, $end]);
            return;
        }

        if ($year) {
            $start = CarbonImmutable::create((int) $year, 1, 1, 0, 0, 0)->startOfDay();
            $end = CarbonImmutable::create((int) $year, 12, 31, 23, 59, 59)->endOfDay();
            $query->whereBetween('start_at', [$start, $end]);
        }
    }

    private function dispatchRetranslationJob(int $candidateId, string $requestedMode): void
    {
        $queueConnection = strtolower(trim((string) config('queue.default', 'sync')));

        // For single-candidate admin action return HTTP response immediately,
        // otherwise UI appears frozen while Ollama runs in-process.
        if ($queueConnection === 'sync') {
            TranslateEventCandidateJob::dispatchAfterResponse($candidateId, true, $requestedMode);
            return;
        }

        TranslateEventCandidateJob::dispatch($candidateId, true, $requestedMode)->afterCommit();
    }

    private function dispatchBatchRetranslationJob(int $candidateId, string $requestedMode): void
    {
        $queueConnection = strtolower(trim((string) config('queue.default', 'sync')));

        if ($queueConnection === 'sync') {
            TranslateEventCandidateJob::dispatchAfterResponse($candidateId, true, $requestedMode);
            return;
        }

        TranslateEventCandidateJob::dispatch($candidateId, true, $requestedMode)->afterCommit();
    }

    private function normalizeRetranslationMode(mixed $value): string
    {
        $normalized = strtolower(trim((string) $value));

        return in_array($normalized, ['ai', 'template', 'mix'], true) ? $normalized : 'ai';
    }

    private function normalizeAiScope(mixed $value): string
    {
        $normalized = strtolower(trim((string) $value));

        return in_array($normalized, ['all', 'missing', 'template'], true) ? $normalized : 'all';
    }

    private function normalizePublishGenerationMode(mixed $value): string
    {
        $normalized = strtolower(trim((string) $value));

        return in_array($normalized, ['template', 'ai', 'mix'], true) ? $normalized : 'template';
    }

    private function resolveCandidatePublishGenerationMode(EventCandidate $candidate, string $publishGenerationMode): string
    {
        if ($publishGenerationMode !== 'mix') {
            return $publishGenerationMode;
        }

        $currentMode = strtolower(trim((string) ($candidate->translation_mode ?? '')));
        if ($currentMode === EventCandidate::TRANSLATION_MODE_TEMPLATE) {
            return 'template';
        }
        if ($currentMode === EventCandidate::TRANSLATION_MODE_MANUAL) {
            return 'manual';
        }

        return 'ai';
    }

    private function runSynchronousRetranslationForPublish(int $candidateId, string $requestedMode): void
    {
        if ($requestedMode === 'manual') {
            return;
        }

        TranslateEventCandidateJob::dispatchSync($candidateId, true, $requestedMode);
    }

    private function archiveCandidateDescriptionVariant(
        EventCandidate $candidate,
        string $reason,
        ?string $requestedPublishMode = null
    ): void {
        $title = trim((string) ($candidate->translated_title ?: $candidate->title ?: ''));
        $description = trim((string) ($candidate->translated_description ?: $candidate->description ?: ''));
        $short = trim((string) ($candidate->short ?? ''));

        if ($title === '' && $description === '' && $short === '') {
            return;
        }

        $payload = $this->decodeCandidateRawPayload((string) ($candidate->raw_payload ?? ''));
        $variants = is_array($payload['description_variants'] ?? null)
            ? array_values(array_filter($payload['description_variants'], static fn ($item): bool => is_array($item)))
            : [];

        $variants[] = array_filter([
            'captured_at' => now()->toIso8601String(),
            'reason' => $reason,
            'requested_publish_mode' => $requestedPublishMode ?: null,
            'mode' => (string) ($candidate->translation_mode ?? ''),
            'translation_status' => (string) ($candidate->translation_status ?? ''),
            'title' => $title !== '' ? $title : null,
            'description' => $description !== '' ? $description : null,
            'short' => $short !== '' ? $short : null,
        ], static fn ($value): bool => $value !== null);

        if (count($variants) > 30) {
            $variants = array_slice($variants, -30);
        }

        $payload['description_variants'] = $variants;
        $encodedPayload = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (! is_string($encodedPayload) || trim($encodedPayload) === '') {
            return;
        }

        $candidate->forceFill([
            'raw_payload' => $encodedPayload,
        ])->save();
    }

    /**
     * @return array<string,mixed>
     */
    private function decodeCandidateRawPayload(string $rawPayload): array
    {
        $trimmed = trim($rawPayload);
        if ($trimmed === '') {
            return [];
        }

        $decoded = json_decode($trimmed, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        return [
            '_source_raw_payload_text' => $rawPayload,
        ];
    }

    private function applyDescriptionModeFilter(Builder $query, ?string $descriptionMode): void
    {
        $normalizedMode = strtolower(trim((string) $descriptionMode));
        if ($normalizedMode === '' || $normalizedMode === 'all') {
            return;
        }

        if ($normalizedMode === 'missing') {
            $query->where(function (Builder $missingQuery): void {
                $missingQuery
                    ->whereNull('translated_description')
                    ->orWhereRaw("TRIM(COALESCE(translated_description, '')) = ''");
            });
            return;
        }

        if ($normalizedMode === 'template') {
            $query->where('translation_mode', EventCandidate::TRANSLATION_MODE_TEMPLATE);
            return;
        }

        if ($normalizedMode === 'manual') {
            $query->where('translation_mode', EventCandidate::TRANSLATION_MODE_MANUAL);
            return;
        }

        if ($normalizedMode === 'ai_refined') {
            $query->where('translation_mode', EventCandidate::TRANSLATION_MODE_AI_REFINED);
            return;
        }

        if ($normalizedMode === 'translated') {
            $query->where('translation_mode', EventCandidate::TRANSLATION_MODE_TRANSLATED);
            return;
        }

        if ($normalizedMode === 'ai') {
            $query->whereIn('translation_mode', [
                EventCandidate::TRANSLATION_MODE_TRANSLATED,
                EventCandidate::TRANSLATION_MODE_AI_REFINED,
            ]);
        }
    }

    private function applyAiScopeFilter(Builder $query, string $scope): void
    {
        if ($scope === 'missing') {
            $query->where(function (Builder $missingQuery): void {
                $missingQuery
                    // "missing" should target candidates that still do not have translated output.
                    // Source/import descriptions are often present in `description`.
                    ->whereNull('translated_description')
                    ->orWhereRaw("TRIM(COALESCE(translated_description, '')) = ''");
            });
            return;
        }

        if ($scope === 'template') {
            $query->where('translation_mode', EventCandidate::TRANSLATION_MODE_TEMPLATE);
        }
    }

    public function cancelTranslationQueue(): \Illuminate\Http\JsonResponse
    {
        $queueConnection = strtolower(trim((string) config('queue.default', 'sync')));

        if ($queueConnection !== 'database') {
            return response()->json(['ok' => false, 'message' => 'Queue driver nie je database.'], 422);
        }

        $deleted = DB::table('jobs')
            ->where('payload', 'like', '%TranslateEventCandidateJob%')
            ->delete();

        Log::info('Translation queue cleared by admin.', ['deleted_jobs' => $deleted]);

        return response()->json(['ok' => true, 'deleted_jobs' => $deleted]);
    }
}
