<?php

namespace App\Services\Performance;

class PerformanceRunResult
{
    /**
     * @param array<string,PerformanceMetric> $results
     */
    public function __construct(
        public readonly array $results,
    ) {
    }

    /**
     * @return list<int>
     */
    public function logIds(): array
    {
        return array_values(array_map(
            static fn (PerformanceMetric $metric): int => (int) $metric->log->id,
            $this->results
        ));
    }
}

