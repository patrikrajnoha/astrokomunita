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

    public function test_it_returns_summary_payload_when_providers_succeed(): void
    {
        config()->set('observing.providers.openaq_api_key', 'test-key');

        Http::fake([
            'https://aa.usno.navy.mil/*' => Http::response($this->usnoPayload(), 200),
            'https://api.open-meteo.com/*' => Http::response($this->openMeteoPayload(), 200),
            'https://api.openaq.org/v3/locations/123/latest*' => Http::response($this->openAqLatestPayload(), 200),
            'https://api.openaq.org/v3/locations*' => Http::response($this->openAqLocationsPayload(), 200),
        ]);

        $response = $this->getJson('/api/observe/summary?lat=48.1486&lon=17.1077&date=2026-02-10&tz=Europe/Bratislava');

        $response->assertOk();
        $response->assertJsonPath('sun.sunrise', '07:07');
        $response->assertJsonPath('moon.phase_name', 'Waning Crescent');
        $response->assertJsonPath('moon.illumination_pct', 91);
        $response->assertJsonPath('moon.warning', 'Mesiac je velmi jasny, slabsie objekty budu horsie viditelne.');
        $response->assertJsonPath('atmosphere.humidity.current_pct', 82);
        $response->assertJsonPath('atmosphere.air_quality.pm25', 12.4);
        $response->assertJsonPath('atmosphere.air_quality.pm10', 28.7);
        $response->assertJsonPath('atmosphere.air_quality.label', 'OK');
    }

    public function test_it_returns_partial_unavailable_sections_when_providers_fail(): void
    {
        config()->set('observing.providers.openaq_api_key', 'test-key');

        Http::fake([
            '*' => Http::response([], 500),
        ]);

        $response = $this->getJson('/api/observe/summary?lat=48.1486&lon=17.1077&date=2026-02-10&tz=Europe/Bratislava');

        $response->assertOk();
        $response->assertJsonPath('sun.status', 'unavailable');
        $response->assertJsonPath('moon.status', 'unavailable');
        $response->assertJsonPath('atmosphere.humidity.status', 'unavailable');
        $response->assertJsonPath('atmosphere.air_quality.status', 'unavailable');
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
            ],
            'hourly' => [
                'time' => [
                    '2026-02-10T18:00',
                    '2026-02-10T19:00',
                    '2026-02-10T20:00',
                    '2026-02-10T21:00',
                ],
                'relative_humidity_2m' => [80, 81, 83, 85],
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

