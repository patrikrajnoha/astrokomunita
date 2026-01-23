<?php

namespace App\Services\EventImport;

use Carbon\CarbonInterface;

class EventCandidateData
{
    public function __construct(
        public string $title,
        public ?string $type,
        public ?CarbonInterface $startAt,
        public ?CarbonInterface $endAt,
        public ?CarbonInterface $maxAt,
        public ?string $short,
        public ?string $description,
        public ?string $sourceUid,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            title: $data['title'],
            type: $data['type'] ?? null,
            startAt: $data['start_at'] ?? null,
            endAt: $data['end_at'] ?? null,
            maxAt: $data['max_at'] ?? null,
            short: $data['short'] ?? null,
            description: $data['description'] ?? null,
            sourceUid: $data['source_uid'] ?? null,
        );
    }
}
