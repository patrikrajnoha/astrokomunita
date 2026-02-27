<?php

namespace App\Services\Crawlers;

use Carbon\CarbonImmutable;

class CandidateItem
{
    public function __construct(
        public string $title,
        public CarbonImmutable $startsAtUtc,
        public ?CarbonImmutable $endsAtUtc,
        public ?string $description,
        public string $sourceUrl,
        public ?string $externalId,
        public array $rawPayload = [],
        public ?string $eventType = null,
        public ?string $canonicalKey = null,
        public ?float $confidenceScore = null,
        public ?array $matchedSources = null,
    ) {
    }
}
