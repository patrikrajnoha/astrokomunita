<?php

namespace Tests\Unit;

use App\Services\Observing\ObservingIndexCalculator;
use App\Services\Observing\ObservingWeights;
use Tests\TestCase;

class ObservingIndexCalculatorTest extends TestCase
{
    /**
     * @return array<string,mixed>
     */
    private function baseObservingInput(): array
    {
        return [
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
        ];
    }

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

    public function test_higher_bortle_class_reduces_observing_index_for_same_inputs(): void
    {
        /** @var ObservingIndexCalculator $calculator */
        $calculator = app(ObservingIndexCalculator::class);

        $baseInput = $this->baseObservingInput();

        $lowLightPollution = $calculator->calculate(ObservingWeights::MODE_DEEP_SKY, array_merge($baseInput, [
            'bortle_class' => 1,
        ]));
        $highLightPollution = $calculator->calculate(ObservingWeights::MODE_DEEP_SKY, array_merge($baseInput, [
            'bortle_class' => 9,
        ]));

        $this->assertGreaterThan($highLightPollution['observing_index'], $lowLightPollution['observing_index']);
        $this->assertArrayHasKey('light_pollution', $lowLightPollution['factors']);
        $this->assertArrayHasKey('light_pollution', $highLightPollution['factors']);
    }

    public function test_light_pollution_factor_bounds_for_bortle_extremes(): void
    {
        /** @var ObservingIndexCalculator $calculator */
        $calculator = app(ObservingIndexCalculator::class);
        $baseInput = $this->baseObservingInput();

        $bortleOne = $calculator->calculate(ObservingWeights::MODE_DEEP_SKY, array_merge($baseInput, ['bortle_class' => 1]));
        $bortleNine = $calculator->calculate(ObservingWeights::MODE_DEEP_SKY, array_merge($baseInput, ['bortle_class' => 9]));

        $this->assertGreaterThanOrEqual(95, $bortleOne['factors']['light_pollution']);
        $this->assertLessThanOrEqual(5, $bortleNine['factors']['light_pollution']);
    }

    public function test_bortle_impact_is_not_extreme_and_is_mode_sensitive(): void
    {
        /** @var ObservingIndexCalculator $calculator */
        $calculator = app(ObservingIndexCalculator::class);
        $baseInput = $this->baseObservingInput();

        $deepSkyLow = $calculator->calculate(ObservingWeights::MODE_DEEP_SKY, array_merge($baseInput, ['bortle_class' => 1]));
        $deepSkyHigh = $calculator->calculate(ObservingWeights::MODE_DEEP_SKY, array_merge($baseInput, ['bortle_class' => 9]));
        $planetsLow = $calculator->calculate(ObservingWeights::MODE_PLANETS, array_merge($baseInput, ['bortle_class' => 1]));
        $planetsHigh = $calculator->calculate(ObservingWeights::MODE_PLANETS, array_merge($baseInput, ['bortle_class' => 9]));

        $deepSkyDelta = $deepSkyLow['observing_index'] - $deepSkyHigh['observing_index'];
        $planetsDelta = $planetsLow['observing_index'] - $planetsHigh['observing_index'];

        $this->assertLessThanOrEqual(25, $deepSkyDelta);
        $this->assertLessThanOrEqual(10, $planetsDelta);
        $this->assertGreaterThan($planetsDelta, $deepSkyDelta);
    }

    public function test_high_light_pollution_alert_is_not_emitted_for_planets_mode(): void
    {
        /** @var ObservingIndexCalculator $calculator */
        $calculator = app(ObservingIndexCalculator::class);
        $baseInput = $this->baseObservingInput();

        $result = $calculator->calculate(ObservingWeights::MODE_PLANETS, array_merge($baseInput, ['bortle_class' => 9]));

        $codes = array_values(array_map(static fn (array $alert): string => (string) ($alert['code'] ?? ''), $result['alerts']));
        $this->assertNotContains('high_light_pollution', $codes);
    }

    public function test_high_light_pollution_alert_is_emitted_for_deep_sky_when_bortle_is_high(): void
    {
        /** @var ObservingIndexCalculator $calculator */
        $calculator = app(ObservingIndexCalculator::class);
        $baseInput = $this->baseObservingInput();

        $result = $calculator->calculate(ObservingWeights::MODE_DEEP_SKY, array_merge($baseInput, ['bortle_class' => 7]));

        $codes = array_values(array_map(static fn (array $alert): string => (string) ($alert['code'] ?? ''), $result['alerts']));
        $this->assertContains('high_light_pollution', $codes);
    }
}
