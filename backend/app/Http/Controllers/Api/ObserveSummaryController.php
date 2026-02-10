<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Observing\ObservingSummaryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ObserveSummaryController extends Controller
{
    public function __construct(
        private readonly ObservingSummaryService $summaryService
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lon' => ['required', 'numeric', 'between:-180,180'],
            'date' => ['required', 'date_format:Y-m-d'],
            'tz' => ['nullable', 'string'],
        ]);

        $lat = (float) $validated['lat'];
        $lon = (float) $validated['lon'];
        $date = (string) $validated['date'];
        $tz = trim((string) ($validated['tz'] ?? config('observing.default_timezone', 'Europe/Bratislava')));

        if ($tz === '') {
            $tz = (string) config('observing.default_timezone', 'Europe/Bratislava');
        }

        $cacheKey = implode(':', [
            'observe_summary',
            number_format($lat, 6, '.', ''),
            number_format($lon, 6, '.', ''),
            $date,
            str_replace(':', '_', $tz),
        ]);

        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            return response()->json($cached);
        }

        $result = $this->summaryService->buildSummary($lat, $lon, $date, $tz);
        $summary = $result['summary'];
        $isPartial = (bool) ($result['is_partial'] ?? false);

        $ttlMinutes = $isPartial
            ? (int) config('observing.cache.partial_ttl_minutes', 5)
            : (int) config('observing.cache.ttl_minutes', 15);

        Cache::put($cacheKey, $summary, now()->addMinutes($ttlMinutes));

        return response()->json($summary);
    }
}

