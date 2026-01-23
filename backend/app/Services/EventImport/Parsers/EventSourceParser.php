<?php

namespace App\Services\EventImport\Parsers;

use App\Services\EventImport\EventCandidateData;

interface EventSourceParser
{
    /**
     * @return array<int, EventCandidateData>
     */
    public function parse(string $payload): array;
}
