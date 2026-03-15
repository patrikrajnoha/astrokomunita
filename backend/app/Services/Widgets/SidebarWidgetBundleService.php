<?php

namespace App\Services\Widgets;

use App\Services\Sky\SkySpaceWeatherService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SidebarWidgetBundleService
{
    /**
     * @var array<string,true>
     */
    private const SUPPORTED_SECTION_KEY_SET = [
        'nasa_apod' => true,
        'next_event' => true,
        'next_eclipse' => true,
        'next_meteor_shower' => true,
        'neo_watchlist' => true,
        'space_weather' => true,
        'aurora_watch' => true,
        'latest_articles' => true,
        'upcoming_events' => true,
    ];

    public function __construct(
        private readonly ArticlesWidgetService $articlesWidgetService,
        private readonly EventWidgetService $eventWidgetService,
        private readonly NasaIotdWidgetService $nasaIotdWidgetService,
        private readonly \App\Services\Sky\SkyNeoWatchlistService $skyNeoWatchlistService,
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
        $spaceWeatherPayload = $this->resolveBundledSpaceWeatherPayload($normalizedSections, $skyContext);
        $auroraPayload = $this->resolveBundledAuroraPayload($normalizedSections, $skyContext, $spaceWeatherPayload);

        foreach ($normalizedSections as $sectionKey) {
            try {
                $payload[$sectionKey] = match ($sectionKey) {
                    'nasa_apod' => $this->nasaIotdWidgetService->payload(),
                    'next_event' => $this->eventWidgetService->nextEvent(),
                    'next_eclipse' => $this->eventWidgetService->nextEclipse(),
                    'next_meteor_shower' => $this->eventWidgetService->nextMeteorShower(),
                    'neo_watchlist' => $this->skyNeoWatchlistService->fetch(),
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
    private function buildSkyCacheKey(string $prefix, array $context): string
    {
        return implode(':', [
            $prefix,
            number_format($context['lat'], 6, '.', ''),
            number_format($context['lon'], 6, '.', ''),
            str_replace(':', '_', $context['tz']),
        ]);
    }
}
