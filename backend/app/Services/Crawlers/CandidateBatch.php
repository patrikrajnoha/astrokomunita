<?php

namespace App\Services\Crawlers;

use App\Enums\EventSource;
use Carbon\CarbonImmutable;

class CandidateBatch
{
    /**
     * @param array<int, CandidateItem> $items
     */
    public function __construct(
        public EventSource $source,
        public int $year,
        public CarbonImmutable $fetchedAt,
        public array $items,
        public int $fetchedBytes = 0,
    ) {
    }
}
