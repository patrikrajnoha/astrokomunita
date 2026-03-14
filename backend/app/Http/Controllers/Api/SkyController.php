<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sky\SkyAstronomyRequest;
use App\Http\Requests\Sky\SkyEphemerisRequest;
use App\Http\Requests\Sky\SkyIssPreviewRequest;
use App\Http\Requests\Sky\SkyLightPollutionRequest;
use App\Http\Requests\Sky\SkyMoonEventsRequest;
use App\Http\Requests\Sky\SkyMoonOverviewRequest;
use App\Http\Requests\Sky\SkyMoonPhasesRequest;
use App\Http\Requests\Sky\SkySpaceWeatherRequest;
use App\Http\Requests\Sky\SkyVisiblePlanetsRequest;
use App\Http\Requests\Sky\SkyWeatherRequest;
use App\Services\Sky\SkyAstronomyService;
use App\Services\Sky\SkyEphemerisService;
use App\Services\Sky\SkyIssPreviewService;
use App\Services\Sky\SkyLightPollutionService;
use App\Services\Sky\SkyMoonEventsService;
use App\Services\Sky\SkyMoonOverviewService;
use App\Services\Sky\SkyMoonPhasesService;
use App\Services\Sky\SkySpaceWeatherService;
use App\Services\Sky\SkyVisiblePlanetsService;
use App\Services\Sky\SkyWeatherService;
use App\Support\ApiResponse;
use App\Support\Sky\SkyContextResolver;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class SkyController extends Controller
{
    public function __construct(
        private readonly SkyContextResolver $contextResolver,
        private readonly SkyWeatherService $skyWeatherService,
        private readonly SkyAstronomyService $skyAstronomyService,
        private readonly SkyVisiblePlanetsService $skyVisiblePlanetsService,
        private readonly SkyIssPreviewService $skyIssPreviewService,
        private readonly SkyLightPollutionService $skyLightPollutionService,
        private readonly SkyMoonPhasesService $skyMoonPhasesService,
        private readonly SkyMoonEventsService $skyMoonEventsService,
        private readonly SkyMoonOverviewService $skyMoonOverviewService,
        private readonly SkyEphemerisService $skyEphemerisService,
        private readonly SkySpaceWeatherService $skySpaceWeatherService
    ) {
    }

    public function weather(SkyWeatherRequest $request): JsonResponse
    {
        $context = $this->contextResolver->resolve($request, $request->validated());
        $cacheKey = $this->buildCacheKey(
            'sky_weather',
            $context['lat'],
            $context['lon'],
            $context['tz'],
            'open_meteo'
        );
        $ttlMinutes = max(1, (int) config('observing.sky.weather_cache_ttl_minutes', 10));

        try {
            $payload = Cache::remember(
                $cacheKey,
                now()->addMinutes($ttlMinutes),
                fn (): array => $this->skyWeatherService->fetch($context['lat'], $context['lon'], $context['tz'])
            );
        } catch (\Throwable) {
            return ApiResponse::error('Sky weather is temporarily unavailable.', null, 503);
        }

        return response()->json($payload);
    }

    public function astronomy(SkyAstronomyRequest $request): JsonResponse
    {
        $context = $this->contextResolver->resolve($request, $request->validated());
        $nowLocal = CarbonImmutable::now($context['tz']);
        $dateKey = $nowLocal->format('Y-m-d');
        $bucketSuffix = $this->resolveTimeBucketSuffix(
            $nowLocal,
            (int) config('observing.sky.astronomy_precision_bucket_minutes', 1)
        );
        $cacheSuffix = $bucketSuffix !== null ? "{$dateKey}:{$bucketSuffix}" : $dateKey;
        $cacheKey = $this->buildCacheKey('sky_astronomy', $context['lat'], $context['lon'], $context['tz'], $cacheSuffix);
        $ttlMinutes = max(
            1,
            (int) config(
                'observing.sky.astronomy_cache_ttl_minutes',
                max(1, ((int) config('observing.sky.astronomy_cache_ttl_hours', 6)) * 60)
            )
        );

        try {
            $payload = Cache::remember(
                $cacheKey,
                now()->addMinutes($ttlMinutes),
                fn (): array => $this->skyAstronomyService->fetch($context['lat'], $context['lon'], $context['tz'])
            );
        } catch (\Throwable) {
            return ApiResponse::error('Sky astronomy data is temporarily unavailable.', null, 503);
        }

        return response()->json($payload);
    }

    public function spaceWeather(SkySpaceWeatherRequest $request): JsonResponse
    {
        $context = $this->contextResolver->resolve($request, $request->validated());
        $cacheKey = $this->buildCacheKey('sky_space_weather', $context['lat'], $context['lon'], $context['tz']);
        $ttlMinutes = max(1, (int) config('observing.sky.space_weather_cache_ttl_minutes', 10));

        $payload = Cache::remember(
            $cacheKey,
            now()->addMinutes($ttlMinutes),
            fn (): array => $this->skySpaceWeatherService->fetch($context['lat'], $context['lon'], $context['tz'])
        );

        return response()->json($payload);
    }

    public function aurora(SkySpaceWeatherRequest $request): JsonResponse
    {
        $context = $this->contextResolver->resolve($request, $request->validated());
        $cacheKey = $this->buildCacheKey('sky_aurora', $context['lat'], $context['lon'], $context['tz']);
        $ttlMinutes = max(
            1,
            (int) config(
                'observing.sky.aurora_cache_ttl_minutes',
                (int) config('observing.sky.space_weather_cache_ttl_minutes', 10)
            )
        );

        $payload = Cache::remember(
            $cacheKey,
            now()->addMinutes($ttlMinutes),
            fn (): array => $this->skySpaceWeatherService->fetchAurora($context['lat'], $context['lon'], $context['tz'])
        );

        return response()->json($payload);
    }

    public function moonPhases(SkyMoonPhasesRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $context = $this->contextResolver->resolve($request, $validated);
        $referenceDate = is_string($validated['date'] ?? null) ? trim((string) $validated['date']) : '';
        $nowLocal = CarbonImmutable::now($context['tz']);
        $resolvedReferenceDate = $referenceDate !== ''
            ? $referenceDate
            : $nowLocal->format('Y-m-d');
        $isCurrentDay = $resolvedReferenceDate === $nowLocal->format('Y-m-d');
        $bucketSuffix = $isCurrentDay
            ? $this->resolveTimeBucketSuffix(
                $nowLocal,
                (int) config('observing.sky.moon_phases_precision_bucket_minutes', 1)
            )
            : null;
        $cacheSuffix = $bucketSuffix !== null ? "{$resolvedReferenceDate}:{$bucketSuffix}" : $resolvedReferenceDate;

        $cacheKey = $this->buildCacheKey(
            'sky_moon_phases',
            $context['lat'],
            $context['lon'],
            $context['tz'],
            $cacheSuffix
        );
        $ttlMinutes = max(
            1,
            (int) config(
                'observing.sky.moon_phases_cache_ttl_minutes',
                max(1, ((int) config('observing.sky.moon_phases_cache_ttl_hours', 12)) * 60)
            )
        );

        try {
            $payload = Cache::remember(
                $cacheKey,
                now()->addMinutes($ttlMinutes),
                fn (): array => $this->skyMoonPhasesService->fetch(
                    $context['lat'],
                    $context['lon'],
                    $context['tz'],
                    $referenceDate !== '' ? $referenceDate : null
                )
            );
        } catch (\Throwable) {
            return ApiResponse::error('Moon phase data is temporarily unavailable.', null, 503);
        }

        return response()->json($payload);
    }

    public function moonEvents(SkyMoonEventsRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $context = $this->contextResolver->resolve($request, $validated);
        $year = is_numeric($validated['year'] ?? null)
            ? (int) round((float) $validated['year'])
            : (int) CarbonImmutable::now($context['tz'])->year;
        $cacheKey = $this->buildCacheKey(
            'sky_moon_events',
            $context['lat'],
            $context['lon'],
            $context['tz'],
            (string) $year
        );
        $ttlMinutes = max(
            1,
            (int) config(
                'observing.sky.moon_events_cache_ttl_minutes',
                max(1, ((int) config('observing.sky.moon_events_cache_ttl_hours', 24)) * 60)
            )
        );

        try {
            $payload = Cache::remember(
                $cacheKey,
                now()->addMinutes($ttlMinutes),
                fn (): array => $this->skyMoonEventsService->fetch($year, $context['tz'])
            );
        } catch (\Throwable) {
            return ApiResponse::error('Moon events data is temporarily unavailable.', null, 503);
        }

        return response()->json($payload);
    }

    public function moonOverview(SkyMoonOverviewRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $context = $this->contextResolver->resolve($request, $validated);
        $referenceDate = is_string($validated['date'] ?? null) ? trim((string) $validated['date']) : '';
        $nowLocal = CarbonImmutable::now($context['tz']);
        $resolvedReferenceDate = $referenceDate !== ''
            ? $referenceDate
            : $nowLocal->format('Y-m-d');
        $isCurrentDay = $resolvedReferenceDate === $nowLocal->format('Y-m-d');
        $bucketSuffix = $isCurrentDay
            ? $this->resolveTimeBucketSuffix(
                $nowLocal,
                (int) config('observing.sky.moon_overview_precision_bucket_minutes', 5)
            )
            : null;
        $cacheSuffix = $bucketSuffix !== null ? "{$resolvedReferenceDate}:{$bucketSuffix}" : $resolvedReferenceDate;
        $cacheKey = $this->buildCacheKey(
            'sky_moon_overview',
            $context['lat'],
            $context['lon'],
            $context['tz'],
            $cacheSuffix
        );
        $ttlMinutes = max(
            1,
            (int) config(
                'observing.sky.moon_overview_cache_ttl_minutes',
                max(1, ((int) config('observing.sky.moon_overview_cache_ttl_hours', 1)) * 60)
            )
        );

        try {
            $payload = Cache::remember(
                $cacheKey,
                now()->addMinutes($ttlMinutes),
                fn (): array => $this->skyMoonOverviewService->fetch(
                    $context['lat'],
                    $context['lon'],
                    $context['tz'],
                    $referenceDate !== '' ? $referenceDate : null
                )
            );
        } catch (\Throwable) {
            return ApiResponse::error('Moon overview data is temporarily unavailable.', null, 503);
        }

        return response()->json($payload);
    }

    public function visiblePlanets(SkyVisiblePlanetsRequest $request): JsonResponse
    {
        $context = $this->contextResolver->resolve($request, $request->validated());
        $dateKey = CarbonImmutable::now($context['tz'])->format('Y-m-d');
        $cacheKey = $this->buildCacheKey('sky_visible_planets', $context['lat'], $context['lon'], $context['tz'], $dateKey);
        $ttlMinutes = max(1, (int) config('observing.sky.visible_planets_cache_ttl_minutes', 10));

        $cachedPayload = Cache::get($cacheKey);
        if ($this->isCacheableSkyPayload($cachedPayload)) {
            return response()->json($cachedPayload);
        }

        $payload = $this->skyVisiblePlanetsService->fetch($context['lat'], $context['lon'], $context['tz']);

        if ($this->isCacheableSkyPayload($payload)) {
            Cache::put($cacheKey, $payload, now()->addMinutes($ttlMinutes));
        } else {
            Cache::forget($cacheKey);
        }

        return response()->json($payload);
    }

    public function ephemeris(SkyEphemerisRequest $request): JsonResponse
    {
        $context = $this->contextResolver->resolve($request, $request->validated());
        $dateKey = CarbonImmutable::now($context['tz'])->format('Y-m-d');
        $bucketSuffix = $this->resolveTimeBucketSuffix(
            CarbonImmutable::now($context['tz']),
            (int) config('observing.sky.ephemeris_precision_bucket_minutes', 10)
        );
        $cacheSuffix = $bucketSuffix !== null ? "{$dateKey}:{$bucketSuffix}" : $dateKey;
        $cacheKey = $this->buildCacheKey('sky_ephemeris', $context['lat'], $context['lon'], $context['tz'], $cacheSuffix);
        $ttlMinutes = max(1, (int) config('observing.sky.ephemeris_cache_ttl_minutes', 30));

        $payload = Cache::remember(
            $cacheKey,
            now()->addMinutes($ttlMinutes),
            fn (): array => $this->skyEphemerisService->fetch($context['lat'], $context['lon'], $context['tz'])
        );

        return response()->json($payload);
    }

    public function issPreview(SkyIssPreviewRequest $request): JsonResponse
    {
        $context = $this->contextResolver->resolve($request, $request->validated());
        $cacheKey = $this->buildCacheKey('sky_iss_preview', $context['lat'], $context['lon'], $context['tz']);
        $ttlMinutes = max(1, (int) config('observing.sky.iss_preview_cache_ttl_minutes', 15));

        $cachedPayload = Cache::get($cacheKey);
        if (is_array($cachedPayload) && !$this->isUnavailableSkyPayload($cachedPayload)) {
            return response()->json($cachedPayload);
        }

        $payload = $this->skyIssPreviewService->fetch($context['lat'], $context['lon'], $context['tz']);

        if (!$this->isUnavailableSkyPayload($payload)) {
            Cache::put($cacheKey, $payload, now()->addMinutes($ttlMinutes));
        } else {
            Cache::forget($cacheKey);
        }

        return response()->json($payload);
    }

    public function lightPollution(SkyLightPollutionRequest $request): JsonResponse
    {
        $context = $this->contextResolver->resolve($request, $request->validated());
        $cacheKey = $this->buildCacheKey('sky_light_pollution', $context['lat'], $context['lon'], $context['tz']);
        $lastKnownCacheKey = $cacheKey.':last_known';
        $ttlHours = max(1, (int) config('observing.sky.light_pollution_cache_ttl_hours', 24));
        $lastKnownTtlHours = max($ttlHours, (int) config('observing.sky.light_pollution_last_known_ttl_hours', 168));

        $cachedPayload = Cache::get($cacheKey);
        if (is_array($cachedPayload) && !$this->isUnavailableSkyPayload($cachedPayload)) {
            return response()->json($cachedPayload);
        }

        $payload = $this->skyLightPollutionService->fetch($context['lat'], $context['lon']);

        if (!$this->isUnavailableSkyPayload($payload)) {
            $freshPayload = [
                ...$payload,
                'sample_at' => CarbonImmutable::now('UTC')->toIso8601String(),
            ];

            Cache::put($cacheKey, $freshPayload, now()->addHours($ttlHours));
            Cache::put($lastKnownCacheKey, $freshPayload, now()->addHours($lastKnownTtlHours));

            return response()->json($freshPayload);
        }

        $lastKnownPayload = Cache::get($lastKnownCacheKey);
        if (
            is_array($lastKnownPayload)
            && !$this->isUnavailableSkyPayload($lastKnownPayload)
            && (
                is_numeric($lastKnownPayload['bortle_class'] ?? null)
                || is_numeric($lastKnownPayload['brightness_value'] ?? null)
            )
        ) {
            return response()->json([
                'bortle_class' => is_numeric($lastKnownPayload['bortle_class'] ?? null)
                    ? (int) round((float) $lastKnownPayload['bortle_class'])
                    : null,
                'brightness_value' => is_numeric($lastKnownPayload['brightness_value'] ?? null)
                    ? round((float) $lastKnownPayload['brightness_value'], 3)
                    : null,
                'confidence' => 'med',
                'source' => 'light_pollution_cached',
                'reason' => 'using_cached_data',
                'measurement' => is_array($lastKnownPayload['measurement'] ?? null)
                    ? $lastKnownPayload['measurement']
                    : null,
                'provenance' => [
                    ...(is_array($lastKnownPayload['provenance'] ?? null) ? $lastKnownPayload['provenance'] : []),
                    'cache_mode' => 'last_known',
                ],
                'sample_at' => is_string($lastKnownPayload['sample_at'] ?? null)
                    ? trim((string) $lastKnownPayload['sample_at'])
                    : null,
            ]);
        }

        Cache::forget($cacheKey);
        return response()->json($payload);
    }

    private function buildCacheKey(string $prefix, float $lat, float $lon, string $tz, ?string $suffix = null): string
    {
        $parts = [
            $prefix,
            number_format($lat, 6, '.', ''),
            number_format($lon, 6, '.', ''),
            str_replace(':', '_', $tz),
        ];

        if ($suffix !== null && $suffix !== '') {
            $parts[] = $suffix;
        }

        return implode(':', $parts);
    }

    private function isUnavailableSkyPayload(mixed $payload): bool
    {
        if (!is_array($payload)) {
            return false;
        }

        $reason = strtolower(trim((string) ($payload['reason'] ?? '')));
        if ($reason === '') {
            return false;
        }

        return str_contains($reason, 'unavailable') || str_contains($reason, 'not_configured');
    }

    private function isCacheableSkyPayload(mixed $payload): bool
    {
        if (!is_array($payload)) {
            return false;
        }

        $reason = strtolower(trim((string) ($payload['reason'] ?? '')));
        if ($reason === 'degraded_contract') {
            return false;
        }

        if ($this->isUnavailableSkyPayload($payload)) {
            return false;
        }

        return $this->hasCacheableVisiblePlanetsContract($payload);
    }

    private function hasCacheableVisiblePlanetsContract(array $payload): bool
    {
        $sampleAt = $payload['sample_at'] ?? null;
        $sunAltitude = $payload['sun_altitude_deg'] ?? null;
        $planets = $payload['planets'] ?? null;

        if (!is_string($sampleAt) || trim($sampleAt) === '' || !is_numeric($sunAltitude) || !is_array($planets)) {
            return false;
        }

        foreach ($planets as $planet) {
            if (!is_array($planet) || !is_numeric($planet['elongation_deg'] ?? null)) {
                return false;
            }
        }

        return true;
    }

    private function resolveTimeBucketSuffix(CarbonImmutable $moment, int $bucketMinutes): ?string
    {
        if ($bucketMinutes < 1) {
            return null;
        }

        $minute = (int) $moment->minute;
        $bucketStartMinute = (int) (floor($minute / $bucketMinutes) * $bucketMinutes);
        $bucketMoment = $moment->setTime((int) $moment->hour, $bucketStartMinute, 0);

        return $bucketMoment->format('Hi');
    }
}
