<?php

namespace App\Services\Crawlers;

use App\Enums\EventSource;

class CrawlerRegistry
{
    public function __construct(
        private readonly AstropixelsCrawlerService $astropixelsCrawler,
    ) {
    }

    public function forSourceKey(string $sourceKey): ?CrawlerInterface
    {
        $enum = EventSource::tryFrom($sourceKey);

        if (! $enum) {
            return null;
        }

        return match ($enum) {
            EventSource::ASTROPIXELS => $this->astropixelsCrawler,
            EventSource::NASA => null,
            EventSource::NASA_WATCH_THE_SKIES => null,
            EventSource::IMO => null,
        };
    }
}
