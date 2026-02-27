<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\CrawlRun;
use App\Models\EventCandidate;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

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
            'q'           => ['nullable', 'string', 'max:200'],
            'per_page'    => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $status     = $validated['status'] ?? EventCandidate::STATUS_PENDING; // default pending
        $type       = $validated['type'] ?? null;
        $rawType    = $validated['raw_type'] ?? null;
        $sourceName = $validated['source_name'] ?? $validated['source'] ?? null;
        $sourceKey  = $validated['source_key'] ?? null;
        $runId      = $validated['run_id'] ?? null;
        $year       = $validated['year'] ?? null;
        $month      = $validated['month'] ?? null;
        $q          = isset($validated['q']) ? trim($validated['q']) : null;
        $perPage    = $validated['per_page'] ?? 20;

        $items = EventCandidate::query()
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
            })
            ->when($year && $month, function ($qq) use ($year, $month) {
                $start = sprintf('%04d-%02d-01 00:00:00', (int) $year, (int) $month);
                $end = date('Y-m-t 23:59:59', strtotime($start));
                $qq->whereBetween('start_at', [$start, $end]);
            })
            ->when($q !== null && $q !== '', function ($qq) use ($q) {
                $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%';

                $qq->where(function ($sub) use ($like) {
                    $sub->where('title', 'like', $like)
                        ->orWhere('short', 'like', $like)
                        ->orWhere('description', 'like', $like);
                });
            })
            ->orderByDesc('max_at')
            ->paginate($perPage)
            ->withQueryString();

        return response()->json($items);
    }

    public function show(EventCandidate $eventCandidate)
    {
        return response()->json($eventCandidate);
    }
}
