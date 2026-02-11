<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Observing\SkySummaryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ObservingSkySummaryController extends Controller
{
    public function __construct(
        private readonly SkySummaryService $skySummaryService
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
        $tz = $this->normalizeTimezoneInput((string) ($validated['tz'] ?? ''));

        $cacheKey = implode(':', [
            'sky_summary',
            number_format($lat, 6, '.', ''),
            number_format($lon, 6, '.', ''),
            $date,
            str_replace(':', '_', $tz),
        ]);

        $ttlMinutes = (int) config('observing.sky_summary.cache_ttl_minutes', 60);

        $summary = Cache::remember($cacheKey, now()->addMinutes($ttlMinutes), function () use ($lat, $lon, $date, $tz): array {
            return $this->skySummaryService->getSummary($lat, $lon, $date, $tz);
        });

        return response()->json($summary);
    }

    private function normalizeTimezoneInput(string $raw): string
    {
        $trimmed = trim($raw, " \t\n\r\0\x0B\"'");

        if ($trimmed !== '' && in_array($trimmed, timezone_identifiers_list(), true)) {
            return $trimmed;
        }

        $fallback = (string) config('app.timezone', 'UTC');
        if (!in_array($fallback, timezone_identifiers_list(), true)) {
            $fallback = 'UTC';
        }

        return $fallback;
    }
}
