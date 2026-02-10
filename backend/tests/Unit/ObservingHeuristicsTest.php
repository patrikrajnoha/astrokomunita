<?php

namespace Tests\Unit;

use App\Services\Observing\ObservingHeuristics;
use Tests\TestCase;

class ObservingHeuristicsTest extends TestCase
{
    public function test_humidity_labels_are_computed_correctly(): void
    {
        $this->assertSame('OK', ObservingHeuristics::humidity(45)['label']);
        $this->assertSame('Pozor', ObservingHeuristics::humidity(70)['label']);
        $this->assertSame('Zle', ObservingHeuristics::humidity(90)['label']);
        $this->assertSame('Nedostupne', ObservingHeuristics::humidity(null)['label']);
    }

    public function test_air_quality_labels_use_pm25_then_pm10_fallback(): void
    {
        $this->assertSame('OK', ObservingHeuristics::airQuality(10.0, null)['label']);
        $this->assertSame('Pozor', ObservingHeuristics::airQuality(20.0, null)['label']);
        $this->assertSame('Zle', ObservingHeuristics::airQuality(40.0, null)['label']);

        $this->assertSame('OK', ObservingHeuristics::airQuality(null, 20.0)['label']);
        $this->assertSame('Pozor', ObservingHeuristics::airQuality(null, 50.0)['label']);
        $this->assertSame('Zle', ObservingHeuristics::airQuality(null, 80.0)['label']);
    }

    public function test_moon_warning_starts_on_90_percent(): void
    {
        $this->assertNull(ObservingHeuristics::moonWarning(89));
        $this->assertNotNull(ObservingHeuristics::moonWarning(90));
    }
}

