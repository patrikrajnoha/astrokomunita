<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserPreference;
use App\Services\Observing\ObservingSummaryService;
use App\Services\Observing\ObservingWeights;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ObserveSummaryController extends Controller
{
    public function __construct(
        private readonly ObservingSummaryService $summaryService,
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
            'bortle_class' => ['nullable', 'integer', 'between:1,9'],
        ]);

        $lat = (float) $validated['lat'];
        $lon = (float) $validated['lon'];
        $date = (string) $validated['date'];
        $tz = $this->sanitizeTimezone((string) ($validated['tz'] ?? ''));
        $mode = $this->observingWeights->sanitizeMode((string) ($validated['mode'] ?? ''));
        $bortleClass = $this->resolveBortleClass($request, $validated);

        $cacheKey = implode(':', [
            'observe_summary',
            number_format($lat, 6, '.', ''),
            number_format($lon, 6, '.', ''),
            $date,
            str_replace(':', '_', $tz),
            'mode_' . $mode,
            'bortle_' . $bortleClass,
        ]);

        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            return response()->json($cached);
        }

        $result = $this->summaryService->getSummary($lat, $lon, $date, $tz, $mode, $bortleClass);
        $summary = $result['summary'];
        $isPartial = (bool) ($result['is_partial'] ?? false);

        $allUnavailable = (bool) ($result['all_unavailable'] ?? false);
        if ($allUnavailable) {
            Cache::put(
                $cacheKey,
                $summary,
                now()->addSeconds((int) config('observing.cache.all_unavailable_ttl_seconds', 90))
            );
        } else {
            $ttlMinutes = $isPartial
                ? (int) config('observing.cache.partial_ttl_minutes', 5)
                : (int) config('observing.cache.ttl_minutes', 15);
            Cache::put($cacheKey, $summary, now()->addMinutes($ttlMinutes));
        }

        return response()->json($summary);
    }

    private function sanitizeTimezone(string $raw): string
    {
        $default = (string) config('observing.default_timezone', 'Europe/Bratislava');
        $trimmed = trim($raw, " \t\n\r\0\x0B\"'");

        if ($trimmed === '') {
            return $default;
        }

        return in_array($trimmed, timezone_identifiers_list(), true) ? $trimmed : $default;
    }

    /**
     * @param array<string,mixed> $validated
     */
    private function resolveBortleClass(Request $request, array $validated): int
    {
        if (isset($validated['bortle_class']) && is_numeric($validated['bortle_class'])) {
            return max(1, min(9, (int) $validated['bortle_class']));
        }

        $user = $request->user();
        if ($user === null) {
            return UserPreference::DEFAULT_BORTLE_CLASS;
        }

        $preference = $user->eventPreference;
        if ($preference === null) {
            return UserPreference::DEFAULT_BORTLE_CLASS;
        }

        return $preference->resolvedBortleClass();
    }
}
