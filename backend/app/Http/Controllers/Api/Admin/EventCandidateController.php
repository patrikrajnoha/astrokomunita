<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\CrawlRun;
use App\Models\EventCandidate;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EventCandidateController extends Controller
{
    public function index(Request $request)
    {
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
            'per_page'    => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $perPage    = $validated['per_page'] ?? 20;

        $items = $this->buildFilteredQuery($validated)
            ->select([
                'id',
                'source_name',
                'source_url',
                'title',
                'status',
                'raw_type',
                'type',
                'canonical_key',
                'confidence_score',
                'matched_sources',
                'max_at',
                'start_at',
                'end_at',
                'short',
                'description',
                'translated_title',
                'translated_description',
                'translation_status',
                'translation_error',
                'translated_at',
                'reviewed_by',
                'reviewed_at',
                'reject_reason',
                'created_at',
                'updated_at',
            ])
            ->orderByDesc('max_at')
            ->paginate($perPage)
            ->withQueryString();

        return response()->json($items);
    }

    public function show(EventCandidate $eventCandidate)
    {
        return response()->json($eventCandidate);
    }

    public function duplicatesPreview(Request $request)
    {
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
            'limit_groups' => ['nullable', 'integer', 'min:1', 'max:50'],
            'per_group' => ['nullable', 'integer', 'min:2', 'max:10'],
        ]);

        $limitGroups = (int) ($validated['limit_groups'] ?? 8);
        $perGroup = (int) ($validated['per_group'] ?? 3);
        $groups = $this->resolveDuplicateGroups($validated, $limitGroups);

        $payloadGroups = array_map(function (array $group) use ($perGroup): array {
            /** @var EventCandidate $keeper */
            $keeper = $group['keeper'];
            /** @var array<int,EventCandidate> $duplicates */
            $duplicates = array_slice($group['duplicates'], 0, $perGroup);

            return [
                'canonical_key' => (string) $group['canonical_key'],
                'count' => (int) $group['count'],
                'keeper' => $this->serializeDuplicateCandidate($keeper),
                'duplicates' => array_map(
                    fn (EventCandidate $candidate): array => $this->serializeDuplicateCandidate($candidate),
                    $duplicates
                ),
                'hidden_duplicates' => max(0, count($group['duplicates']) - count($duplicates)),
            ];
        }, $groups);

        $totalDuplicateCandidates = array_sum(array_map(
            static fn (array $group): int => max(0, ((int) $group['count']) - 1),
            $groups
        ));

        return response()->json([
            'status' => 'ok',
            'summary' => [
                'group_count' => count($groups),
                'duplicate_candidates' => $totalDuplicateCandidates,
                'limit_groups' => $limitGroups,
                'per_group' => $perGroup,
            ],
            'groups' => $payloadGroups,
        ]);
    }

    public function mergeDuplicates(Request $request)
    {
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
            'limit_groups' => ['nullable', 'integer', 'min:1', 'max:100'],
            'dry_run' => ['sometimes', 'boolean'],
        ]);

        $limitGroups = (int) ($validated['limit_groups'] ?? 25);
        $dryRun = (bool) ($validated['dry_run'] ?? false);
        $groups = $this->resolveDuplicateGroups($validated, $limitGroups);
        $reviewerId = (int) $request->user()->id;
        $mergedCandidates = 0;

        if (! $dryRun && $groups !== []) {
            DB::transaction(function () use ($groups, $reviewerId, &$mergedCandidates): void {
                foreach ($groups as $group) {
                    /** @var array<int,EventCandidate> $duplicates */
                    $duplicates = $group['duplicates'];
                    $duplicateIds = array_values(array_filter(array_map(
                        static fn (EventCandidate $row): int => (int) $row->id,
                        $duplicates
                    )));

                    if ($duplicateIds === []) {
                        continue;
                    }

                    $affected = EventCandidate::query()
                        ->whereIn('id', $duplicateIds)
                        ->where('status', EventCandidate::STATUS_PENDING)
                        ->update([
                            'status' => EventCandidate::STATUS_DUPLICATE,
                            'reviewed_by' => $reviewerId,
                            'reviewed_at' => now(),
                            'reject_reason' => 'auto_duplicate_merge',
                        ]);

                    $mergedCandidates += (int) $affected;
                }
            });
        }

        $responseGroups = array_map(function (array $group): array {
            /** @var EventCandidate $keeper */
            $keeper = $group['keeper'];

            return [
                'canonical_key' => (string) $group['canonical_key'],
                'keeper_id' => (int) $keeper->id,
                'duplicate_ids' => array_values(array_filter(array_map(
                    static fn (EventCandidate $candidate): int => (int) $candidate->id,
                    $group['duplicates']
                ))),
                'count' => (int) $group['count'],
            ];
        }, $groups);

        return response()->json([
            'status' => $dryRun ? 'dry_run' : 'ok',
            'dry_run' => $dryRun,
            'summary' => [
                'group_count' => count($groups),
                'merged_candidates' => $dryRun
                    ? array_sum(array_map(static fn (array $group): int => count($group['duplicates']), $groups))
                    : $mergedCandidates,
                'limit_groups' => $limitGroups,
            ],
            'groups' => $responseGroups,
        ]);
    }

    /**
     * @param array<string,mixed> $validated
     */
    private function buildFilteredQuery(array $validated): Builder
    {
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

        return $query->when($q !== null && $q !== '', function ($qq) use ($q) {
            $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%';

            $qq->where(function ($sub) use ($like) {
                $sub->where('title', 'like', $like)
                    ->orWhere('short', 'like', $like)
                    ->orWhere('description', 'like', $like);
            });
        });
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

    /**
     * @param array<string,mixed> $validated
     * @return array<int,array{
     *   canonical_key:string,
     *   count:int,
     *   keeper:EventCandidate,
     *   duplicates:array<int,EventCandidate>
     * }>
     */
    private function resolveDuplicateGroups(array $validated, int $limitGroups): array
    {
        $baseQuery = $this->buildFilteredQuery(array_merge($validated, [
            'status' => EventCandidate::STATUS_PENDING,
        ]))
            ->whereNotNull('canonical_key')
            ->where('canonical_key', '!=', '');

        $groupKeys = (clone $baseQuery)
            ->select('canonical_key', DB::raw('COUNT(*) as duplicate_count'))
            ->groupBy('canonical_key')
            ->havingRaw('COUNT(*) > 1')
            ->orderByDesc('duplicate_count')
            ->limit($limitGroups)
            ->get();

        $groups = [];

        foreach ($groupKeys as $group) {
            $canonicalKey = trim((string) $group->canonical_key);
            if ($canonicalKey === '') {
                continue;
            }

            $rows = (clone $baseQuery)
                ->where('canonical_key', $canonicalKey)
                ->get([
                    'id',
                    'canonical_key',
                    'title',
                    'source_name',
                    'status',
                    'start_at',
                    'confidence_score',
                    'matched_sources',
                    'created_at',
                    'updated_at',
                ]);

            if ($rows->count() <= 1) {
                continue;
            }

            $sorted = $rows->sort(function (EventCandidate $left, EventCandidate $right): int {
                return $this->duplicateRank($right) <=> $this->duplicateRank($left);
            })->values();

            $keeper = $sorted->first();
            if (! $keeper instanceof EventCandidate) {
                continue;
            }

            $duplicates = $sorted
                ->slice(1)
                ->filter(fn ($row): bool => $row instanceof EventCandidate)
                ->values()
                ->all();

            $groups[] = [
                'canonical_key' => $canonicalKey,
                'count' => $sorted->count(),
                'keeper' => $keeper,
                'duplicates' => $duplicates,
            ];
        }

        return $groups;
    }

    /**
     * Higher tuple means better keeper candidate.
     *
     * @return array{0:float,1:int,2:int,3:int}
     */
    private function duplicateRank(EventCandidate $candidate): array
    {
        $confidence = is_numeric((string) $candidate->confidence_score) ? (float) $candidate->confidence_score : 0.0;
        $matchedSources = is_array($candidate->matched_sources) ? count($candidate->matched_sources) : 0;
        $updatedAt = $candidate->updated_at ? CarbonImmutable::instance($candidate->updated_at)->timestamp : 0;

        return [
            round($confidence, 4),
            $matchedSources,
            $updatedAt,
            (int) $candidate->id,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function serializeDuplicateCandidate(EventCandidate $candidate): array
    {
        return [
            'id' => (int) $candidate->id,
            'title' => (string) $candidate->title,
            'source_name' => (string) $candidate->source_name,
            'status' => (string) $candidate->status,
            'start_at' => $candidate->start_at?->toISOString(),
            'confidence_score' => $candidate->confidence_score !== null ? (float) $candidate->confidence_score : null,
            'matched_sources' => is_array($candidate->matched_sources) ? array_values($candidate->matched_sources) : [],
        ];
    }
}
