<?php

namespace App\Services\Crawlers\Imo;

use App\Services\Crawlers\CandidateItem;

class ImoParseResult
{
    /**
     * @param array<int, CandidateItem> $items
     * @param array<int, string> $diagnostics
     */
    public function __construct(
        public array $items,
        public array $diagnostics = [],
    ) {
    }
}

