<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Observing\SkySummaryService;
use App\Services\Observing\ObservingWeights;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ObservingSkySummaryController extends Controller
{
    public function __construct(
        private readonly SkySummaryService $skySummaryService,
        private readonly ObservingWeights $observingWeights
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lon' => ['required', 'numeric', 'between:-180,180'],
            'date' => ['required', 'date_format:Y-m-d'],
            'tz' => ['nullable', 'string'],
            'mode' => ['nullable', 'string'],
        ]);

        $lat = (float) $validated['lat'];
        $lon = (float) $validated['lon'];
        $date = (string) $validated['date'];
        $tz = (string) ($validated['tz'] ?? '');
        $mode = $this->observingWeights->sanitizeMode((string) ($validated['mode'] ?? ''));

        $cacheKey = implode(':', [
            'sky_summary',
            number_format($lat, 6, '.', ''),
            number_format($lon, 6, '.', ''),
            $date,
            str_replace(':', '_', $tz),
            'mode_' . $mode,
        ]);

        $ttlMinutes = (int) config('observing.sky_summary.cache_ttl_minutes', 60);

        $summary = Cache::remember($cacheKey, now()->addMinutes($ttlMinutes), function () use ($lat, $lon, $date, $tz): array {
            return $this->skySummaryService->getSummary($lat, $lon, $date, $tz);
        });

        return response()->json($summary);
    }

}
