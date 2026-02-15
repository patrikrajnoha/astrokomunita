<?php

namespace App\Services\Crawlers\Astropixels;

use App\Services\Crawlers\CandidateItem;

class AstropixelsParseResult
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
