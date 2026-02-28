<?php

namespace Tests\Feature\Sky;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SkyVisiblePlanetsEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_filtered_visible_planets_from_microservice(): void
    {
        Cache::flush();

        Http::fake([
            '*sky-summary*' => Http::response([
                'moon' => null,
                'planets' => [
                    [
                        'name' => 'Jupiter',
                        'alt_max_deg' => 64.7,
                        'az_at_best_deg' => 152.5,
                        'direction' => 'SE',
                        'best_from' => '19:40',
                        'best_to' => '23:10',
                    ],
                    [
                        'name' => 'Venus',
                        'alt_max_deg' => 4.7,
                        'az_at_best_deg' => 260.1,
                        'direction' => 'W',
                        'best_from' => '18:20',
                        'best_to' => '19:00',
                    ],
                    [
                        'name' => 'Saturn',
                        'alt_max_deg' => 5.0,
                        'az_at_best_deg' => 210.1,
                        'direction' => 'SW',
                        'best_from' => '19:10',
                        'best_to' => '20:00',
                    ],
                    [
                        'name' => 'Mars',
                        'alt_max_deg' => 18.3,
                        'az_at_best_deg' => 98.3,
                        'direction' => 'E',
                        'best_from' => '20:15',
                        'best_to' => '01:10',
                    ],
                ],
            ], 200),
        ]);

        $response = $this->getJson('/api/sky/visible-planets?lat=48.1486&lon=17.1077&tz=Europe/Bratislava');

        $response->assertOk()
            ->assertJsonStructure([
                'planets' => [
                    '*' => [
                        'name',
                        'altitude_deg',
                        'azimuth_deg',
                        'direction',
                    ],
                ],
            ]);

        $planets = $response->json('planets');
        $this->assertCount(3, $planets);
        $this->assertSame('Jupiter', $planets[0]['name']);
        $this->assertSame('Mars', $planets[1]['name']);
        $this->assertSame('Saturn', $planets[2]['name']);
        $this->assertArrayHasKey('best_time_window', $planets[0]);
        $this->assertSame('excellent', $planets[0]['quality']);
        $this->assertSame('good', $planets[1]['quality']);
        $this->assertSame('low', $planets[2]['quality']);
        $this->assertGreaterThan($planets[1]['altitude_deg'], $planets[0]['altitude_deg']);
    }

    public function test_it_excludes_planets_below_five_degrees_and_marks_excellent_quality(): void
    {
        Cache::flush();

        Http::fake([
            '*sky-summary*' => Http::response([
                'moon' => null,
                'planets' => [
                    [
                        'name' => 'Jupiter',
                        'alt_max_deg' => 64.0,
                        'az_at_best_deg' => 181.3,
                        'direction' => 'S',
                        'best_from' => '18:10',
                        'best_to' => '03:00',
                    ],
                    [
                        'name' => 'Mercury',
                        'alt_max_deg' => 4.9,
                        'az_at_best_deg' => 92.0,
                        'direction' => 'E',
                        'best_from' => '18:20',
                        'best_to' => '18:40',
                    ],
                ],
            ], 200),
        ]);

        $response = $this->getJson('/api/sky/visible-planets?lat=48.1486&lon=17.1077&tz=Europe/Bratislava');

        $response->assertOk()
            ->assertJsonCount(1, 'planets')
            ->assertJsonPath('planets.0.name', 'Jupiter')
            ->assertJsonPath('planets.0.quality', 'excellent');
    }

    public function test_it_returns_fallback_payload_when_sky_service_is_unavailable(): void
    {
        Cache::flush();

        Http::fake([
            '*sky-summary*' => Http::response(['message' => 'Service unavailable'], 503),
        ]);

        $response = $this->getJson('/api/sky/visible-planets?lat=48.1486&lon=17.1077&tz=Europe/Bratislava');

        $response->assertOk()
            ->assertJsonPath('planets', [])
            ->assertJsonPath('reason', 'sky_service_unavailable');
    }

    public function test_it_caches_visible_planets_payload(): void
    {
        Cache::flush();

        Http::fake([
            '*sky-summary*' => Http::sequence()
                ->push([
                    'moon' => null,
                    'planets' => [
                        [
                            'name' => 'Saturn',
                            'alt_max_deg' => 22.1,
                            'az_at_best_deg' => 134.4,
                            'direction' => 'SE',
                            'best_from' => '21:00',
                            'best_to' => '01:20',
                        ],
                    ],
                ], 200)
                ->push([
                    'moon' => null,
                    'planets' => [
                        [
                            'name' => 'Mercury',
                            'alt_max_deg' => 11.0,
                            'az_at_best_deg' => 90.0,
                            'direction' => 'E',
                            'best_from' => '18:10',
                            'best_to' => '19:10',
                        ],
                    ],
                ], 200),
        ]);

        $first = $this->getJson('/api/sky/visible-planets?lat=48.1486&lon=17.1077&tz=Europe/Bratislava')->assertOk();
        $second = $this->getJson('/api/sky/visible-planets?lat=48.1486&lon=17.1077&tz=Europe/Bratislava')->assertOk();

        $this->assertSame($first->json('planets.0.name'), $second->json('planets.0.name'));
        Http::assertSentCount(1);
    }
}
