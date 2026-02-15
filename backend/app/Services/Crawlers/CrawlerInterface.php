<?php

namespace App\Services\Crawlers;

interface CrawlerInterface
{
    public function fetchCandidates(CrawlContext $context): CandidateBatch;
}
