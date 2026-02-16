<?php

namespace App\Services\Crawlers;

use App\Enums\EventSource;

interface CrawlerInterface
{
    public function source(): EventSource;

    public function fetchCandidates(CrawlContext $context): CandidateBatch;
}
