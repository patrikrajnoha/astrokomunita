<?php

namespace App\Services\Widgets;

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
        'latest_articles' => true,
        'upcoming_events' => true,
    ];

    public function __construct(
        private readonly ArticlesWidgetService $articlesWidgetService,
        private readonly EventWidgetService $eventWidgetService,
        private readonly NasaIotdWidgetService $nasaIotdWidgetService,
        private readonly \App\Services\Sky\SkyNeoWatchlistService $skyNeoWatchlistService,
    ) {
    }

    /**
     * @param  list<string>  $sectionKeys
     * @return array<string,mixed>
     */
    public function payloadForSections(array $sectionKeys): array
    {
        $payload = [];

        foreach ($this->normalizeRequestedSections($sectionKeys) as $sectionKey) {
            try {
                $payload[$sectionKey] = match ($sectionKey) {
                    'nasa_apod' => $this->nasaIotdWidgetService->payload(),
                    'next_event' => $this->eventWidgetService->nextEvent(),
                    'next_eclipse' => $this->eventWidgetService->nextEclipse(),
                    'next_meteor_shower' => $this->eventWidgetService->nextMeteorShower(),
                    'neo_watchlist' => $this->skyNeoWatchlistService->fetch(),
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
}
