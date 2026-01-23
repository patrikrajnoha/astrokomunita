<?php

namespace App\Services\EventImport;

class EventImportResult
{
    public function __construct(
        public int $total,
        public int $imported,
        public int $duplicates,
    ) {
    }
}
