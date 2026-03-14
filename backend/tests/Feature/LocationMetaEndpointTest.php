<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LocationMetaEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_locations_endpoint_returns_open_meteo_results_with_limit(): void
    {
        Config::set('observing.providers.open_meteo_geocoding_url', 'https://geocoding-api.open-meteo.com/v1/search');

        Http::fake([
            'https://geocoding-api.open-meteo.com/v1/search*' => Http::response([
                'results' => [
                    [
                        'id' => 123,
                        'name' => 'Bratislava',
                        'latitude' => 48.1486,
                        'longitude' => 17.1077,
                        'country' => 'Slovakia',
                        'country_code' => 'SK',
                        'admin1' => 'Bratislava',
                        'timezone' => 'Europe/Bratislava',
                    ],
                    [
                        'id' => 124,
                        'name' => 'Bratislava',
                        'latitude' => 49.2500,
                        'longitude' => 18.7500,
                        'country' => 'Czechia',
                        'country_code' => 'CZ',
                        'admin1' => 'Moravia',
                        'timezone' => 'Europe/Prague',
                    ],
                ],
            ], 200),
        ]);

        $response = $this->getJson('/api/meta/locations?q=Br&limit=8');

        $response->assertOk();
        $rows = $response->json('data');

        $this->assertIsArray($rows);
        $this->assertNotEmpty($rows);
        $this->assertLessThanOrEqual(8, count($rows));
        $this->assertStringStartsWith('Bratislava', $rows[0]['label']);
        $this->assertSame('open_meteo:123', $rows[0]['place_id']);
        $this->assertSame('SK', $rows[0]['country']);
        $this->assertSame('Europe/Bratislava', $rows[0]['timezone']);
    }

    public function test_locations_endpoint_falls_back_to_config_when_provider_unavailable(): void
    {
        Config::set('observing.providers.open_meteo_geocoding_url', 'https://geocoding-api.open-meteo.com/v1/search');

        Http::fake([
            'https://geocoding-api.open-meteo.com/v1/search*' => Http::response(['error' => true], 503),
        ]);

        $response = $this->getJson('/api/meta/locations?q=Br&limit=8');

        $response->assertOk();
        $rows = $response->json('data');

        $this->assertIsArray($rows);
        $this->assertNotEmpty($rows);
        $this->assertSame('Bratislava, Slovensko', $rows[0]['label']);
        $this->assertSame('sk:bratislava', $rows[0]['place_id']);
        $this->assertNull($rows[0]['timezone']);
        $this->assertNull($rows[0]['country']);
    }
}
