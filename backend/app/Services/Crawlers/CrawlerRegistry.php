<?php

namespace App\Services\Crawlers;

use App\Enums\EventSource;

class CrawlerRegistry
{
    public function __construct(
        private readonly AstropixelsCrawlerService $astropixelsCrawler,
        private readonly NasaCrawlerService $nasaCrawler,
        private readonly NasaWatchTheSkiesCrawlerService $nasaWatchTheSkiesCrawler,
        private readonly ImoCrawlerService $imoCrawler,
    ) {}

    public function forSourceKey(string $sourceKey): ?CrawlerInterface
    {
        $normalizedKey = strtolower(trim($sourceKey));
        if ($normalizedKey === 'nasa_wts') {
            $normalizedKey = EventSource::NASA_WATCH_THE_SKIES->value;
        }

        $enum = EventSource::tryFrom($normalizedKey);

        if (! $enum) {
            return null;
        }

        return match ($enum) {
            EventSource::ASTROPIXELS => $this->astropixelsCrawler,
            EventSource::NASA => $this->nasaCrawler,
            EventSource::NASA_WATCH_THE_SKIES => $this->nasaWatchTheSkiesCrawler,
            EventSource::IMO => $this->imoCrawler,
        };
    }
}
