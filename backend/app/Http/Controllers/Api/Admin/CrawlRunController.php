<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\CrawlRun;
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

        return response()->json($items);
    }

    public function show(CrawlRun $crawlRun)
    {
        return response()->json($crawlRun);
    }
}
