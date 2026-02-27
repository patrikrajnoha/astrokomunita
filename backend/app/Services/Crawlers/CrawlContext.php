<?php

namespace App\Services\Crawlers;

use Carbon\CarbonImmutable;

class CrawlContext
{
    public function __construct(
        public int $year,
        public ?CarbonImmutable $from = null,
        public ?CarbonImmutable $to = null,
        public string $timezone = 'Europe/Bratislava',
        public bool $dryRun = false,
    ) {
    }
}
