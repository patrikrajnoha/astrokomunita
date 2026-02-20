<?php

namespace Tests\Unit;

use App\Services\Observing\ObservingIndexCalculator;
use App\Services\Observing\ObservingWeights;
use Tests\TestCase;

class ObservingIndexCalculatorTest extends TestCase
{
    public function test_it_calculates_index_and_overall_for_planets_mode(): void
    {
        /** @var ObservingIndexCalculator $calculator */
        $calculator = app(ObservingIndexCalculator::class);

        $result = $calculator->calculate(ObservingWeights::MODE_PLANETS, [
            'humidity_pct' => 54,
            'cloud_cover_pct' => 28,
            'pm25' => 12.0,
            'pm10' => 26.0,
            'moon_illumination_pct' => 45,
            'wind_speed_kmh' => 9.5,
            'sun' => [
                'status' => 'ok',
                'sunset' => '17:15',
                'sunrise' => '07:02',
                'civil_twilight_end' => '17:45',
                'civil_twilight_begin' => '06:32',
            ],
            'date' => '2026-02-20',
            'tz' => 'Europe/Bratislava',
        ]);

        $this->assertSame(ObservingWeights::MODE_PLANETS, $result['observing_mode']);
        $this->assertIsInt($result['observing_index']);
        $this->assertArrayHasKey('seeing', $result['factors']);
        $this->assertArrayHasKey('cloud', $result['weights']);
        $this->assertArrayHasKey('label', $result['overall']);
        $this->assertArrayHasKey('reason', $result['overall']);
        $this->assertArrayHasKey('alert_level', $result['overall']);
        $this->assertStringContainsString('100 - min(40,wind_kmh)', (string) $result['seeing']['formula']);
    }

    public function test_it_calculates_best_time_from_hourly_series(): void
    {
        /** @var ObservingIndexCalculator $calculator */
        $calculator = app(ObservingIndexCalculator::class);

        $best = $calculator->calculateBestTime(ObservingWeights::MODE_DEEP_SKY, [
            ['local_time' => '18:00', 'humidity_pct' => 84, 'cloud_cover_pct' => 75, 'wind_speed_kmh' => 21.0],
            ['local_time' => '21:00', 'humidity_pct' => 66, 'cloud_cover_pct' => 38, 'wind_speed_kmh' => 11.0],
            ['local_time' => '23:00', 'humidity_pct' => 59, 'cloud_cover_pct' => 22, 'wind_speed_kmh' => 8.0],
        ], [
            'pm25' => 10.0,
            'pm10' => 20.0,
            'moon_illumination_pct' => 35,
            'sun' => [
                'status' => 'ok',
                'sunset' => '17:15',
                'sunrise' => '07:02',
                'civil_twilight_end' => '17:45',
                'civil_twilight_begin' => '06:32',
            ],
            'date' => '2026-02-20',
            'tz' => 'Europe/Bratislava',
        ]);

        $this->assertSame('23:00', $best['best_time_local']);
        $this->assertIsInt($best['best_time_index']);
        $this->assertNotNull($best['best_time_reason']);
        $this->assertCount(3, $best['series']);
    }
}
