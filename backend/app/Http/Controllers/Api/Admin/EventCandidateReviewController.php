<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\TranslateEventCandidateJob;
use App\Models\CrawlRun;
use App\Models\Event;
use App\Models\EventCandidate;
use App\Services\Events\EventCandidatePublisher;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class EventCandidateReviewController extends Controller
{
    public function __construct(
        private readonly EventCandidatePublisher $publisher,
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

        $event = $this->publisher->approve($candidate, (int) $request->user()->id);

        return response()->json([
            'ok' => true,
            'candidate' => $candidate->fresh(),
            'published_event_id' => $event->id,
        ]);
    }

    public function approveBatch(Request $request)
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }

        $validated = $request->validate([
            'status'      => ['nullable', 'string', 'max:50'],
            'type'        => ['nullable', 'string', 'max:100'],
            'raw_type'    => ['nullable', 'string', 'max:100'],
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
        ]);

        $status = $validated['status'] ?? EventCandidate::STATUS_PENDING;
        $type = $validated['type'] ?? null;
        $rawType = $validated['raw_type'] ?? null;
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

        $query->when($q !== null && $q !== '', function ($qq) use ($q) {
            $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%';
            $qq->where(function ($sub) use ($like) {
                $sub->where('title', 'like', $like)
                    ->orWhere('short', 'like', $like)
                    ->orWhere('description', 'like', $like);
            });
        });

        $ids = $query
            ->where('status', EventCandidate::STATUS_PENDING)
            ->orderByDesc('id')
            ->limit($limit)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($ids === []) {
            return response()->json([
                'ok' => true,
                'published' => 0,
                'failed' => 0,
                'total_selected' => 0,
                'limit_applied' => $limit,
            ]);
        }

        $published = 0;
        $failed = 0;
        $reviewerId = (int) $request->user()->id;

        foreach ($ids as $candidateId) {
            try {
                $candidate = EventCandidate::query()->find($candidateId);
                if (! $candidate || $candidate->status !== EventCandidate::STATUS_PENDING) {
                    $failed++;
                    continue;
                }

                $this->publisher->approve($candidate, $reviewerId);
                $published++;
            } catch (\Throwable $exception) {
                $failed++;
                Log::warning('Event candidate batch approve failed', [
                    'candidate_id' => $candidateId,
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
        $candidate->update([
            'translation_status' => EventCandidate::TRANSLATION_PENDING,
            'translation_error' => null,
        ]);

        try {
            $this->dispatchRetranslationJob((int) $candidate->id);
        } catch (\Throwable $exception) {
            Log::warning('Event candidate retranslation dispatch failed', [
                'candidate_id' => (int) $candidate->id,
                'error' => $exception->getMessage(),
            ]);
        }

        return response()->json([
            'ok' => true,
            'candidate' => $candidate->fresh(),
            'message' => 'Preklad bol zaradeny do fronty.',
        ]);
    }

    public function retranslateBatch(Request $request)
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }

        $validated = $request->validate([
            'status'      => ['nullable', 'string', 'max:50'],
            'type'        => ['nullable', 'string', 'max:100'],
            'raw_type'    => ['nullable', 'string', 'max:100'],
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
        ]);

        $status = $validated['status'] ?? EventCandidate::STATUS_PENDING;
        $type = $validated['type'] ?? null;
        $rawType = $validated['raw_type'] ?? null;
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

        $query->when($q !== null && $q !== '', function ($qq) use ($q) {
            $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%';
            $qq->where(function ($sub) use ($like) {
                $sub->where('title', 'like', $like)
                    ->orWhere('short', 'like', $like)
                    ->orWhere('description', 'like', $like);
            });
        });

        $ids = $query->orderByDesc('id')->limit($limit)->pluck('id')->map(fn ($id) => (int) $id)->all();
        if ($ids === []) {
            return response()->json([
                'ok' => true,
                'queued' => 0,
                'failed' => 0,
                'total_selected' => 0,
            ]);
        }

        EventCandidate::query()
            ->whereIn('id', $ids)
            ->update([
                'translation_status' => EventCandidate::TRANSLATION_PENDING,
                'translation_error' => null,
            ]);

        $queued = 0;
        $failed = 0;

        foreach ($ids as $candidateId) {
            try {
                TranslateEventCandidateJob::dispatch($candidateId, true);
                $queued++;
            } catch (\Throwable $exception) {
                $failed++;
                Log::warning('Event candidate batch retranslation dispatch failed', [
                    'candidate_id' => $candidateId,
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
                'message' => 'Prelozeny nadpis je povinny.',
            ], 422);
        }

        $translatedDescription = $translatedDescription !== null && $translatedDescription !== ''
            ? $translatedDescription
            : null;

        $short = $translatedDescription !== null
            ? mb_substr($translatedDescription, 0, 180)
            : mb_substr($translatedTitle, 0, 180);

        $candidate->update([
            'translated_title' => $translatedTitle,
            'translated_description' => $translatedDescription,
            'short' => $short,
            'description' => $translatedDescription,
            'translation_status' => EventCandidate::TRANSLATION_DONE,
            'translation_error' => null,
            'translated_at' => now(),
        ]);

        if ($candidate->published_event_id) {
            Event::query()
                ->whereKey((int) $candidate->published_event_id)
                ->update([
                    'title' => $translatedTitle,
                    'description' => $translatedDescription,
                    'short' => $short,
                ]);
        }

        return response()->json([
            'ok' => true,
            'candidate' => $candidate->fresh(),
        ]);
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

    private function dispatchRetranslationJob(int $candidateId): void
    {
        $queueConnection = strtolower(trim((string) config('queue.default', 'sync')));
        $allowSyncQueue = (bool) config('translation.allow_sync_queue', false);
        $preferSyncInLocal = (bool) config('translation.events.prefer_sync_in_local', true);

        $shouldDispatchSync = $queueConnection === 'sync'
            || $allowSyncQueue
            || ($preferSyncInLocal && app()->environment('local'));

        if ($shouldDispatchSync) {
            TranslateEventCandidateJob::dispatchSync($candidateId, true);
            return;
        }

        TranslateEventCandidateJob::dispatch($candidateId, true)->afterCommit();
    }
}

