<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\CrawlRun;
use App\Models\EventCandidate;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class CrawlRunController extends Controller
{
    public function index(Request $request)
    {
        $perPage = (int) $request->query('per_page', 20);
        $perPage = max(1, min($perPage, 100));

        $sourceName = $request->query('source_name');
        $sourceKey = $request->query('source_key');
        $year = $request->query('year');
        $status = $request->query('status');
        $from = $request->query('from');
        $to = $request->query('to');

        $items = CrawlRun::query()
            ->when($sourceName, fn ($q) => $q->where('source_name', $sourceName))
            ->when($sourceKey, fn ($q) => $q->whereHas('eventSource', fn ($sub) => $sub->where('key', $sourceKey)))
            ->when($year, fn ($q) => $q->where('year', (int) $year))
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($from, fn ($q) => $q->where('started_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('started_at', '<=', $to))
            ->orderByDesc('started_at')
            ->paginate($perPage);

        $items->setCollection(
            $items->getCollection()->map(function (CrawlRun $run): array {
                $payload = $run->toArray();
                $payload['translation'] = $this->buildTranslationSummary($run);

                return $payload;
            })
        );

        return response()->json($items);
    }

    public function show(CrawlRun $crawlRun)
    {
        return response()->json($crawlRun);
    }

    private function buildTranslationSummary(CrawlRun $run): array
    {
        $query = EventCandidate::query()
            ->when($run->event_source_id !== null, function ($qq) use ($run) {
                $qq->where('event_source_id', (int) $run->event_source_id);
            }, function ($qq) use ($run) {
                $qq->where('source_name', (string) $run->source_name);
            });

        $startedAt = $run->started_at ? CarbonImmutable::instance($run->started_at) : null;
        $finishedAt = $run->finished_at ? CarbonImmutable::instance($run->finished_at) : null;

        if ($startedAt !== null) {
            $windowEnd = $finishedAt;
            if ($windowEnd === null || $windowEnd->lessThan($startedAt)) {
                $windowEnd = $startedAt->addMinutes(30);
            } else {
                $windowEnd = $windowEnd->addMinutes(5);
            }

            $query->whereBetween('created_at', [$startedAt, $windowEnd]);
        }

        $row = $query
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN translation_status = 'done' THEN 1 ELSE 0 END) as done")
            ->selectRaw("SUM(CASE WHEN translation_status = 'failed' THEN 1 ELSE 0 END) as failed")
            ->selectRaw("SUM(CASE WHEN translation_status = 'pending' THEN 1 ELSE 0 END) as pending")
            ->selectRaw("SUM(CASE WHEN translation_status = 'done' AND TRIM(COALESCE(translated_title, '')) <> '' AND TRIM(COALESCE(translated_description, '')) <> '' THEN 1 ELSE 0 END) as done_both")
            ->selectRaw("SUM(CASE WHEN translation_status = 'done' AND TRIM(COALESCE(translated_title, '')) <> '' AND TRIM(COALESCE(translated_description, '')) = '' THEN 1 ELSE 0 END) as done_title_only")
            ->selectRaw("SUM(CASE WHEN translation_status = 'done' AND TRIM(COALESCE(translated_title, '')) = '' AND TRIM(COALESCE(translated_description, '')) <> '' THEN 1 ELSE 0 END) as done_description_only")
            ->selectRaw("SUM(CASE WHEN translation_status = 'done' AND TRIM(COALESCE(translated_title, '')) = '' AND TRIM(COALESCE(translated_description, '')) = '' THEN 1 ELSE 0 END) as done_without_text")
            ->first();

        return [
            'total' => (int) ($row?->total ?? 0),
            'done' => (int) ($row?->done ?? 0),
            'failed' => (int) ($row?->failed ?? 0),
            'pending' => (int) ($row?->pending ?? 0),
            'done_breakdown' => [
                'both' => (int) ($row?->done_both ?? 0),
                'title_only' => (int) ($row?->done_title_only ?? 0),
                'description_only' => (int) ($row?->done_description_only ?? 0),
                'without_text' => (int) ($row?->done_without_text ?? 0),
            ],
        ];
    }
}
