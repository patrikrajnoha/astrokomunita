<?php

namespace App\Services\Widgets;

use App\Services\Sky\SkyAstronomyService;
use App\Services\Sky\SkyVisiblePlanetsService;
use App\Services\Sky\SkyWeatherService;
use App\Services\Sky\SkySpaceWeatherService;
use App\Services\Sky\SkyUpcomingLaunchesService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SidebarWidgetBundleService
{
    /**
     * @var array<string,true>
     */
    private const SUPPORTED_SECTION_KEY_SET = [
        'observing_conditions' => true,
        'observing_weather' => true,
        'night_sky' => true,
        'iss_pass' => true,
        'nasa_apod' => true,
        'next_event' => true,
        'next_eclipse' => true,
        'next_meteor_shower' => true,
        'neo_watchlist' => true,
        'upcoming_launches' => true,
        'space_weather' => true,
        'aurora_watch' => true,
        'latest_articles' => true,
        'upcoming_events' => true,
    ];

    public function __construct(
        private readonly ArticlesWidgetService $articlesWidgetService,
        private readonly EventWidgetService $eventWidgetService,
        private readonly NasaIotdWidgetService $nasaIotdWidgetService,
        private readonly SkyWeatherService $skyWeatherService,
        private readonly SkyAstronomyService $skyAstronomyService,
        private readonly SkyVisiblePlanetsService $skyVisiblePlanetsService,
        private readonly \App\Services\Sky\SkyNeoWatchlistService $skyNeoWatchlistService,
        private readonly SkyUpcomingLaunchesService $skyUpcomingLaunchesService,
        private readonly SkySpaceWeatherService $skySpaceWeatherService,
    ) {
    }

    /**
     * @param  list<string>  $sectionKeys
     * @param  array{lat:float,lon:float,tz:string}|null  $skyContext
     * @return array<string,mixed>
     */
    public function payloadForSections(array $sectionKeys, ?array $skyContext = null): array
    {
        $payload = [];
        $normalizedSections = $this->normalizeRequestedSections($sectionKeys);
        $weatherPayload = $this->resolveBundledWeatherPayload($normalizedSections, $skyContext);
        $astronomyPayload = $this->resolveBundledAstronomyPayload($normalizedSections, $skyContext);
        $visiblePlanetsPayload = $this->resolveBundledVisiblePlanetsPayload($normalizedSections, $skyContext);
        $issPreviewPayload = $this->resolveBundledIssPreviewPayload($normalizedSections, $skyContext);
        $lightPollutionPayload = $this->resolveBundledLightPollutionPayload($normalizedSections, $skyContext);
        $spaceWeatherPayload = $this->resolveBundledSpaceWeatherPayload($normalizedSections, $skyContext);
        $auroraPayload = $this->resolveBundledAuroraPayload($normalizedSections, $skyContext, $spaceWeatherPayload);

        foreach ($normalizedSections as $sectionKey) {
            try {
                $payload[$sectionKey] = match ($sectionKey) {
                    'observing_conditions' => $this->bundleObservingConditionsPayload($weatherPayload, $astronomyPayload),
                    'observing_weather' => $this->bundleObservingWeatherPayload($weatherPayload),
                    'night_sky' => $this->bundleNightSkyPayload($astronomyPayload, $visiblePlanetsPayload, $lightPollutionPayload),
                    'iss_pass' => $this->bundleIssPassPayload($issPreviewPayload),
                    'nasa_apod' => $this->nasaIotdWidgetService->payload(),
                    'next_event' => $this->eventWidgetService->nextEvent(),
                    'next_eclipse' => $this->eventWidgetService->nextEclipse(),
                    'next_meteor_shower' => $this->eventWidgetService->nextMeteorShower(),
                    'neo_watchlist' => $this->skyNeoWatchlistService->fetch(),
                    'upcoming_launches' => $this->skyUpcomingLaunchesService->fetch(),
                    'space_weather' => $spaceWeatherPayload,
                    'aurora_watch' => $auroraPayload,
                    'latest_articles' => $this->articlesWidgetService->payload(),
                    'upcoming_events' => $this->eventWidgetService->upcoming(),
                    default => null,
                };
            } catch (\Throwable $exception) {
                Log::warning('Sidebar widget bundle section failed.', [
                    'section_key' => $sectionKey,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        return array_filter(
            $payload,
            static fn (mixed $entry): bool => $entry !== null
        );
    }

    /**
     * @param  list<string>  $sectionKeys
     * @return list<string>
     */
    public function normalizeRequestedSections(array $sectionKeys): array
    {
        return array_values(array_filter(array_unique(array_map(
            static fn (mixed $value): string => trim((string) $value),
            $sectionKeys,
        )), static fn (string $sectionKey): bool => $sectionKey !== '' && isset(self::SUPPORTED_SECTION_KEY_SET[$sectionKey])));
    }

    /**
     * @param  array<string,mixed>|null  $weatherPayload
     * @param  array<string,mixed>|null  $astronomyPayload
     * @return array<string,mixed>|null
     */
    private function bundleObservingConditionsPayload(?array $weatherPayload, ?array $astronomyPayload): ?array
    {
        $payload = array_filter([
            'weather' => $weatherPayload,
            'astronomy' => $astronomyPayload,
        ], static fn (mixed $value): bool => $value !== null);

        return $payload !== [] ? $payload : null;
    }

    /**
     * @param  array<string,mixed>|null  $weatherPayload
     * @return array<string,mixed>|null
     */
    private function bundleObservingWeatherPayload(?array $weatherPayload): ?array
    {
        return $weatherPayload !== null ? ['weather' => $weatherPayload] : null;
    }

    /**
     * @param  array<string,mixed>|null  $astronomyPayload
     * @param  array<string,mixed>|null  $visiblePlanetsPayload
     * @param  array<string,mixed>|null  $lightPollutionPayload
     * @return array<string,mixed>|null
     */
    private function bundleNightSkyPayload(
        ?array $astronomyPayload,
        ?array $visiblePlanetsPayload,
        ?array $lightPollutionPayload
    ): ?array {
        $payload = array_filter([
            'astronomy' => $astronomyPayload,
            'visible_planets' => $visiblePlanetsPayload,
            'light_pollution' => $lightPollutionPayload,
        ], static fn (mixed $value): bool => $value !== null);

        return $payload !== [] ? $payload : null;
    }

    /**
     * @param  array<string,mixed>|null  $issPreviewPayload
     * @return array<string,mixed>|null
     */
    private function bundleIssPassPayload(?array $issPreviewPayload): ?array
    {
        return $issPreviewPayload !== null ? ['iss_preview' => $issPreviewPayload] : null;
    }

    /**
     * @param  list<string>  $sectionKeys
     * @param  array{lat:float,lon:float,tz:string}|null  $skyContext
     * @return array<string,mixed>|null
     */
    private function resolveBundledWeatherPayload(array $sectionKeys, ?array $skyContext): ?array
    {
        if (! $this->requiresAnySection($sectionKeys, ['observing_conditions', 'observing_weather']) || $skyContext === null) {
            return null;
        }

        return Cache::remember(
            $this->buildSkyCacheKey('sky_weather', $skyContext, 'open_meteo'),
            now()->addMinutes(max(1, (int) config('observing.sky.weather_cache_ttl_minutes', 10))),
            fn (): array => $this->skyWeatherService->fetch(
                $skyContext['lat'],
                $skyContext['lon'],
                $skyContext['tz']
            )
        );
    }

    /**
     * @param  list<string>  $sectionKeys
     * @param  array{lat:float,lon:float,tz:string}|null  $skyContext
     * @return array<string,mixed>|null
     */
    private function resolveBundledAstronomyPayload(array $sectionKeys, ?array $skyContext): ?array
    {
        if (! $this->requiresAnySection($sectionKeys, ['observing_conditions', 'night_sky']) || $skyContext === null) {
            return null;
        }

        $nowLocal = CarbonImmutable::now($skyContext['tz']);
        $dateKey = $nowLocal->format('Y-m-d');
        $bucketSuffix = $this->resolveTimeBucketSuffix(
            $nowLocal,
            (int) config('observing.sky.astronomy_precision_bucket_minutes', 1)
        );
        $cacheSuffix = $bucketSuffix !== null ? "{$dateKey}:{$bucketSuffix}" : $dateKey;
        $ttlMinutes = max(
            1,
            (int) config(
                'observing.sky.astronomy_cache_ttl_minutes',
                max(1, ((int) config('observing.sky.astronomy_cache_ttl_hours', 6)) * 60)
            )
        );

        return Cache::remember(
            $this->buildSkyCacheKey('sky_astronomy', $skyContext, $cacheSuffix),
            now()->addMinutes($ttlMinutes),
            fn (): array => $this->skyAstronomyService->fetch(
                $skyContext['lat'],
                $skyContext['lon'],
                $skyContext['tz']
            )
        );
    }

    /**
     * @param  list<string>  $sectionKeys
     * @param  array{lat:float,lon:float,tz:string}|null  $skyContext
     * @return array<string,mixed>|null
     */
    private function resolveBundledVisiblePlanetsPayload(array $sectionKeys, ?array $skyContext): ?array
    {
        if (! in_array('night_sky', $sectionKeys, true) || $skyContext === null) {
            return null;
        }

        $dateKey = CarbonImmutable::now($skyContext['tz'])->format('Y-m-d');
        $cacheKey = $this->buildSkyCacheKey('sky_visible_planets', $skyContext, $dateKey);
        $ttlMinutes = max(1, (int) config('observing.sky.visible_planets_cache_ttl_minutes', 10));

        $cachedPayload = Cache::get($cacheKey);
        if ($this->isCacheableVisiblePlanetsPayload($cachedPayload)) {
            return $cachedPayload;
        }

        $payload = $this->skyVisiblePlanetsService->fetch(
            $skyContext['lat'],
            $skyContext['lon'],
            $skyContext['tz']
        );

        if ($this->isCacheableVisiblePlanetsPayload($payload)) {
            Cache::put($cacheKey, $payload, now()->addMinutes($ttlMinutes));
        } else {
            Cache::forget($cacheKey);
        }

        return $payload;
    }

    /**
     * @param  list<string>  $sectionKeys
     * @param  array{lat:float,lon:float,tz:string}|null  $skyContext
     * @return array<string,mixed>|null
     */
    private function resolveBundledIssPreviewPayload(array $sectionKeys, ?array $skyContext): ?array
    {
        if (! in_array('iss_pass', $sectionKeys, true) || $skyContext === null) {
            return null;
        }

        $cacheKey = $this->buildSkyCacheKey('sky_iss_preview', $skyContext);
        $cachedPayload = Cache::get($cacheKey);

        if (is_array($cachedPayload) && ! $this->isUnavailableSkyPayload($cachedPayload)) {
            return $cachedPayload;
        }

        return null;
    }

    /**
     * @param  list<string>  $sectionKeys
     * @param  array{lat:float,lon:float,tz:string}|null  $skyContext
     * @return array<string,mixed>|null
     */
    private function resolveBundledLightPollutionPayload(array $sectionKeys, ?array $skyContext): ?array
    {
        if (! in_array('night_sky', $sectionKeys, true) || $skyContext === null) {
            return null;
        }

        $cacheKey = $this->buildSkyCacheKey('sky_light_pollution', $skyContext);
        $lastKnownCacheKey = $cacheKey.':last_known';
        $cachedPayload = Cache::get($cacheKey);

        if (is_array($cachedPayload) && ! $this->isUnavailableSkyPayload($cachedPayload)) {
            return $cachedPayload;
        }

        $lastKnownPayload = Cache::get($lastKnownCacheKey);
        if (
            is_array($lastKnownPayload)
            && ! $this->isUnavailableSkyPayload($lastKnownPayload)
            && (
                is_numeric($lastKnownPayload['bortle_class'] ?? null)
                || is_numeric($lastKnownPayload['brightness_value'] ?? null)
            )
        ) {
            return [
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
            ];
        }

        return null;
    }

    /**
     * @param  list<string>  $sectionKeys
     * @param  list<string>  $required
     */
    private function requiresAnySection(array $sectionKeys, array $required): bool
    {
        foreach ($required as $sectionKey) {
            if (in_array($sectionKey, $sectionKeys, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $sectionKeys
     * @param  array{lat:float,lon:float,tz:string}|null  $skyContext
     * @return array<string,mixed>|null
     */
    private function resolveBundledSpaceWeatherPayload(array $sectionKeys, ?array $skyContext): ?array
    {
        if (! in_array('space_weather', $sectionKeys, true) || $skyContext === null) {
            return null;
        }

        return Cache::remember(
            $this->buildSkyCacheKey('sky_space_weather', $skyContext),
            now()->addMinutes(max(1, (int) config('observing.sky.space_weather_cache_ttl_minutes', 10))),
            fn (): array => $this->skySpaceWeatherService->fetch(
                $skyContext['lat'],
                $skyContext['lon'],
                $skyContext['tz']
            )
        );
    }

    /**
     * @param  list<string>  $sectionKeys
     * @param  array{lat:float,lon:float,tz:string}|null  $skyContext
     * @param  array<string,mixed>|null  $spaceWeatherPayload
     * @return array<string,mixed>|null
     */
    private function resolveBundledAuroraPayload(array $sectionKeys, ?array $skyContext, ?array $spaceWeatherPayload): ?array
    {
        if (! in_array('aurora_watch', $sectionKeys, true) || $skyContext === null) {
            return null;
        }

        if ($spaceWeatherPayload !== null) {
            return $this->auroraPayloadFromSpaceWeather($spaceWeatherPayload);
        }

        return Cache::remember(
            $this->buildSkyCacheKey('sky_aurora', $skyContext),
            now()->addMinutes(max(
                1,
                (int) config(
                    'observing.sky.aurora_cache_ttl_minutes',
                    (int) config('observing.sky.space_weather_cache_ttl_minutes', 10)
                )
            )),
            fn (): array => $this->skySpaceWeatherService->fetchAurora(
                $skyContext['lat'],
                $skyContext['lon'],
                $skyContext['tz']
            )
        );
    }

    /**
     * @param  array<string,mixed>  $spaceWeatherPayload
     * @return array<string,mixed>|null
     */
    private function auroraPayloadFromSpaceWeather(array $spaceWeatherPayload): ?array
    {
        $aurora = is_array($spaceWeatherPayload['aurora'] ?? null) ? $spaceWeatherPayload['aurora'] : null;
        if ($aurora === null) {
            return null;
        }

        return [
            ...$aurora,
            'updated_at' => is_string($aurora['updated_at'] ?? null)
                ? trim((string) $aurora['updated_at'])
                : (is_string($spaceWeatherPayload['updated_at'] ?? null)
                    ? trim((string) $spaceWeatherPayload['updated_at'])
                    : null),
            'source' => [
                'provider' => 'noaa_swpc',
                'label' => 'NOAA SWPC OVATION',
                'url' => 'https://www.swpc.noaa.gov/products/aurora-30-minute-forecast',
            ],
            'sources' => [
                'aurora' => is_array($spaceWeatherPayload['sources'] ?? null)
                    ? (string) (($spaceWeatherPayload['sources']['aurora'] ?? '') ?: 'https://services.swpc.noaa.gov/json/ovation_aurora_latest.json')
                    : 'https://services.swpc.noaa.gov/json/ovation_aurora_latest.json',
            ],
        ];
    }

    /**
     * @param  array{lat:float,lon:float,tz:string}  $context
     */
    private function buildSkyCacheKey(string $prefix, array $context, ?string $suffix = null): string
    {
        $parts = [
            $prefix,
            number_format($context['lat'], 6, '.', ''),
            number_format($context['lon'], 6, '.', ''),
            str_replace(':', '_', $context['tz']),
        ];

        if ($suffix !== null && $suffix !== '') {
            $parts[] = $suffix;
        }

        return implode(':', $parts);
    }

    private function isUnavailableSkyPayload(mixed $payload): bool
    {
        if (! is_array($payload)) {
            return false;
        }

        $reason = strtolower(trim((string) ($payload['reason'] ?? '')));
        if ($reason === '') {
            return false;
        }

        return str_contains($reason, 'unavailable') || str_contains($reason, 'not_configured');
    }

    private function isCacheableVisiblePlanetsPayload(mixed $payload): bool
    {
        if (! is_array($payload)) {
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

    /**
     * @param  array<string,mixed>  $payload
     */
    private function hasCacheableVisiblePlanetsContract(array $payload): bool
    {
        $sampleAt = $payload['sample_at'] ?? null;
        $sunAltitude = $payload['sun_altitude_deg'] ?? null;
        $planets = $payload['planets'] ?? null;

        if (! is_string($sampleAt) || trim($sampleAt) === '' || ! is_numeric($sunAltitude) || ! is_array($planets)) {
            return false;
        }

        foreach ($planets as $planet) {
            if (! is_array($planet) || ! is_numeric($planet['elongation_deg'] ?? null)) {
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
