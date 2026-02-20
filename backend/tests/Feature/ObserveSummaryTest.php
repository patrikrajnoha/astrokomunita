<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ObserveSummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_validates_required_query_params(): void
    {
        $this->getJson('/api/observe/summary')->assertStatus(422);
    }

    public function test_it_returns_extended_summary_payload_when_providers_succeed(): void
    {
        config()->set('observing.providers.openaq.key', 'test-key');

        Http::fake([
            'https://aa.usno.navy.mil/*' => Http::response($this->usnoPayload(), 200),
            'https://api.open-meteo.com/*' => Http::response($this->openMeteoPayload(), 200),
            'https://api.openaq.org/v3/locations/123/latest*' => Http::response($this->openAqLatestPayload(), 200),
            'https://api.openaq.org/v3/locations*' => Http::response($this->openAqLocationsPayload(), 200),
        ]);

        $response = $this->getJson('/api/observe/summary?lat=48.1486&lon=17.1077&date=2026-02-10&tz=Europe/Bratislava&mode=deep_sky');

        $response->assertOk();
        $response->assertJsonPath('sun.sunrise', '07:07');
        $response->assertJsonPath('moon.phase_name', 'Waning Crescent');
        $response->assertJsonPath('moon.illumination_pct', 91);
        $response->assertJsonPath('atmosphere.humidity.current_pct', 82);
        $response->assertJsonPath('atmosphere.cloud_cover.current_pct', 68);
        $response->assertJsonPath('atmosphere.air_quality.pm25', 12.4);
        $response->assertJsonPath('observing_mode', 'deep_sky');
        $response->assertJsonStructure([
            'observing_index',
            'observing_mode',
            'factors' => ['humidity', 'cloud', 'air_quality', 'moon', 'darkness', 'seeing'],
            'weights',
            'alerts',
            'overall' => ['label', 'reason', 'alert_level'],
            'best_time_local',
            'best_time_index',
            'best_time_reason',
            'timeline' => ['hourly', 'sunset', 'sunrise', 'civil_twilight_end', 'civil_twilight_begin'],
        ]);
        $this->assertIsInt($response->json('observing_index'));
        $this->assertIsArray($response->json('timeline.hourly'));
    }

    public function test_it_returns_partial_unavailable_sections_when_weather_provider_times_out(): void
    {
        config()->set('observing.providers.openaq.key', 'test-key');

        Http::fake([
            'https://aa.usno.navy.mil/*' => Http::response($this->usnoPayload(), 200),
            'https://api.open-meteo.com/*' => function () {
                throw new \Illuminate\Http\Client\ConnectionException('Timeout');
            },
            'https://api.openaq.org/v3/locations/123/latest*' => Http::response($this->openAqLatestPayload(), 200),
            'https://api.openaq.org/v3/locations*' => Http::response($this->openAqLocationsPayload(), 200),
        ]);

        $response = $this->getJson('/api/observe/summary?lat=48.1486&lon=17.1077&date=2026-02-10&tz=Europe/Bratislava&mode=planets');

        $response->assertOk();
        $response->assertJsonPath('sun.status', 'ok');
        $response->assertJsonPath('moon.status', 'ok');
        $response->assertJsonPath('atmosphere.humidity.status', 'unavailable');
        $response->assertJsonPath('atmosphere.cloud_cover.status', 'unavailable');
        $response->assertJsonPath('atmosphere.air_quality.status', 'ok');
        $this->assertIsInt($response->json('observing_index'));
    }

    private function usnoPayload(): array
    {
        return [
            'properties' => [
                'data' => [
                    'curphase' => 'Waning Crescent',
                    'fracillum' => '91%',
                    'sundata' => [
                        ['phen' => 'Begin Civil Twilight', 'time' => '06:34'],
                        ['phen' => 'Rise', 'time' => '07:07'],
                        ['phen' => 'Set', 'time' => '17:05'],
                        ['phen' => 'End Civil Twilight', 'time' => '17:38'],
                    ],
                ],
            ],
        ];
    }

    private function openMeteoPayload(): array
    {
        return [
            'current' => [
                'relative_humidity_2m' => 82,
                'cloud_cover' => 68,
                'wind_speed_10m' => 15.4,
            ],
            'hourly' => [
                'time' => [
                    '2026-02-10T18:00',
                    '2026-02-10T19:00',
                    '2026-02-10T20:00',
                    '2026-02-10T21:00',
                ],
                'relative_humidity_2m' => [80, 81, 83, 85],
                'cloud_cover' => [40, 45, 35, 30],
                'windspeed_10m' => [12.0, 14.0, 13.0, 12.0],
            ],
        ];
    }

    private function openAqLocationsPayload(): array
    {
        return [
            'results' => [
                ['id' => 123, 'distance' => 1200],
                ['id' => 456, 'distance' => 4000],
            ],
        ];
    }

    private function openAqLatestPayload(): array
    {
        return [
            'results' => [
                [
                    'measurements' => [
                        ['parameter' => 'pm25', 'value' => 12.4],
                        ['parameter' => 'pm10', 'value' => 28.7],
                    ],
                ],
            ],
        ];
    }
}
