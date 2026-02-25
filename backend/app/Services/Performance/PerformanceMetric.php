<?php

namespace App\Services\Performance;

use App\Models\PerformanceLog;

class PerformanceMetric
{
    /**
     * @param array<string,mixed> $data
     */
    public function __construct(
        public readonly PerformanceLog $log,
        public readonly array $data,
    ) {
    }
}

