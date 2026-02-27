<?php

namespace Tests\Unit;

use App\Services\Performance\PerformanceRunner;
use Tests\TestCase;

class PerformanceRunnerPercentileTest extends TestCase
{
    public function test_percentile_calculates_expected_p95(): void
    {
        /** @var PerformanceRunner $runner */
        $runner = app(PerformanceRunner::class);

        $value = $runner->percentile([10, 20, 30, 40, 50], 95);

        $this->assertSame(48.0, $value);
    }

    public function test_percentile_handles_single_value(): void
    {
        /** @var PerformanceRunner $runner */
        $runner = app(PerformanceRunner::class);

        $value = $runner->percentile([7], 95);

        $this->assertSame(7.0, $value);
    }
}

