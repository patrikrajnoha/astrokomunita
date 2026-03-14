<?php

namespace Tests\Feature\Sky;

use App\Services\Sky\SkyVisiblePlanetsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SkyVisiblePlanetsEndpointTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('observing.providers.jpl_horizons_url', '');
    }

    public function test_it_returns_filtered_visible_planets_from_microservice(): void
    {
        Cache::flush();

        Http::fake([
            '*sky-summary*' => Http::response([
                'moon' => null,
                'sample_at' => '2026-02-27T20:15:00+01:00',
                'sun_altitude_deg' => -18.4,
                'planets' => [
                    [
                        'name' => 'Jupiter',
                        'alt_max_deg' => 64.7,
                        'az_at_best_deg' => 152.5,
                        'elongation_deg' => 132.1,
                        'direction' => 'SE',
                        'best_from' => '19:40',
                        'best_to' => '23:10',
                    ],
                    [
                        'name' => 'Venus',
                        'alt_max_deg' => 4.7,
                        'az_at_best_deg' => 260.1,
                        'elongation_deg' => 22.0,
                        'direction' => 'W',
                        'best_from' => '18:20',
                        'best_to' => '19:00',
                    ],
                    [
                        'name' => 'Saturn',
                        'alt_max_deg' => 5.0,
                        'az_at_best_deg' => 210.1,
                        'elongation_deg' => 48.5,
                        'direction' => 'SW',
                        'best_from' => '19:10',
                        'best_to' => '20:00',
                    ],
                    [
                        'name' => 'Mars',
                        'alt_max_deg' => 18.3,
                        'az_at_best_deg' => 98.3,
                        'elongation_deg' => 73.4,
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
                'sample_at',
                'sun_altitude_deg',
                'planets' => [
                    '*' => [
                        'name',
                        'altitude_deg',
                        'azimuth_deg',
                        'elongation_deg',
                        'direction',
                    ],
                ],
            ]);

        $planets = $response->json('planets');
        $this->assertSame('2026-02-27T20:15:00+01:00', $response->json('sample_at'));
        $this->assertSame(-18.4, $response->json('sun_altitude_deg'));
        $this->assertCount(3, $planets);
        $this->assertSame('Jupiter', $planets[0]['name']);
        $this->assertSame('Mars', $planets[1]['name']);
        $this->assertSame('Saturn', $planets[2]['name']);
        $this->assertArrayHasKey('best_time_window', $planets[0]);
        $this->assertSame(132.1, $planets[0]['elongation_deg']);
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
                'sample_at' => '2026-02-27T19:15:00+01:00',
                'sun_altitude_deg' => -14.0,
                'planets' => [
                    [
                        'name' => 'Jupiter',
                        'alt_max_deg' => 64.0,
                        'az_at_best_deg' => 181.3,
                        'elongation_deg' => 120.0,
                        'direction' => 'S',
                        'best_from' => '18:10',
                        'best_to' => '03:00',
                    ],
                    [
                        'name' => 'Mercury',
                        'alt_max_deg' => 4.9,
                        'az_at_best_deg' => 92.0,
                        'elongation_deg' => 18.0,
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
            ->assertJsonPath('sample_at', null)
            ->assertJsonPath('sun_altitude_deg', null)
            ->assertJsonPath('reason', 'sky_service_unavailable');
    }

    public function test_it_marks_legacy_microservice_payload_as_degraded_contract_and_does_not_cache_it(): void
    {
        Cache::flush();

        Http::fake([
            '*sky-summary*' => Http::sequence()
                ->push([
                    'moon' => null,
                    'planets' => [
                        [
                            'name' => 'Jupiter',
                            'alt_max_deg' => 44.0,
                            'az_at_best_deg' => 180.0,
                            'direction' => 'S',
                            'best_from' => '19:00',
                            'best_to' => '22:00',
                        ],
                    ],
                ], 200)
                ->push([
                    'moon' => null,
                    'sample_at' => '2026-02-27T20:45:00+01:00',
                    'sun_altitude_deg' => -19.5,
                    'planets' => [
                        [
                            'name' => 'Mars',
                            'alt_max_deg' => 21.0,
                            'az_at_best_deg' => 110.0,
                            'elongation_deg' => 47.2,
                            'direction' => 'E',
                            'best_from' => '20:10',
                            'best_to' => '00:40',
                        ],
                    ],
                ], 200),
        ]);

        $first = $this->getJson('/api/sky/visible-planets?lat=48.1486&lon=17.1077&tz=Europe/Bratislava');
        $second = $this->getJson('/api/sky/visible-planets?lat=48.1486&lon=17.1077&tz=Europe/Bratislava');

        $first->assertOk()
            ->assertJsonPath('reason', 'degraded_contract')
            ->assertJsonPath('planets', []);

        $second->assertOk()
            ->assertJsonMissingPath('reason')
            ->assertJsonPath('sample_at', '2026-02-27T20:45:00+01:00')
            ->assertJsonPath('planets.0.name', 'Mars');

        Http::assertSentCount(2);
    }

    public function test_it_marks_payload_without_planets_array_as_degraded_contract_and_does_not_cache_it(): void
    {
        Cache::flush();

        Http::fake([
            '*sky-summary*' => Http::sequence()
                ->push([
                    'moon' => null,
                    'sample_at' => '2026-02-27T20:40:00+01:00',
                    'sun_altitude_deg' => -19.0,
                ], 200)
                ->push([
                    'moon' => null,
                    'sample_at' => '2026-02-27T20:45:00+01:00',
                    'sun_altitude_deg' => -19.5,
                    'planets' => [
                        [
                            'name' => 'Mars',
                            'alt_max_deg' => 21.0,
                            'az_at_best_deg' => 110.0,
                            'elongation_deg' => 47.2,
                            'direction' => 'E',
                            'best_from' => '20:10',
                            'best_to' => '00:40',
                        ],
                    ],
                ], 200),
        ]);

        $first = $this->getJson('/api/sky/visible-planets?lat=48.1486&lon=17.1077&tz=Europe/Bratislava');
        $second = $this->getJson('/api/sky/visible-planets?lat=48.1486&lon=17.1077&tz=Europe/Bratislava');

        $first->assertOk()
            ->assertJsonPath('reason', 'degraded_contract')
            ->assertJsonPath('planets', []);

        $second->assertOk()
            ->assertJsonMissingPath('reason')
            ->assertJsonPath('sample_at', '2026-02-27T20:45:00+01:00')
            ->assertJsonPath('planets.0.name', 'Mars');

        Http::assertSentCount(2);
    }

    public function test_it_marks_payload_with_null_planets_as_degraded_contract_and_does_not_cache_it(): void
    {
        Cache::flush();

        Http::fake([
            '*sky-summary*' => Http::sequence()
                ->push([
                    'moon' => null,
                    'sample_at' => '2026-02-27T20:40:00+01:00',
                    'sun_altitude_deg' => -19.0,
                    'planets' => null,
                ], 200)
                ->push([
                    'moon' => null,
                    'sample_at' => '2026-02-27T20:45:00+01:00',
                    'sun_altitude_deg' => -19.5,
                    'planets' => [
                        [
                            'name' => 'Mars',
                            'alt_max_deg' => 21.0,
                            'az_at_best_deg' => 110.0,
                            'elongation_deg' => 47.2,
                            'direction' => 'E',
                            'best_from' => '20:10',
                            'best_to' => '00:40',
                        ],
                    ],
                ], 200),
        ]);

        $first = $this->getJson('/api/sky/visible-planets?lat=48.1486&lon=17.1077&tz=Europe/Bratislava');
        $second = $this->getJson('/api/sky/visible-planets?lat=48.1486&lon=17.1077&tz=Europe/Bratislava');

        $first->assertOk()
            ->assertJsonPath('reason', 'degraded_contract')
            ->assertJsonPath('planets', []);

        $second->assertOk()
            ->assertJsonMissingPath('reason')
            ->assertJsonPath('sample_at', '2026-02-27T20:45:00+01:00')
            ->assertJsonPath('planets.0.name', 'Mars');

        Http::assertSentCount(2);
    }

    public function test_it_does_not_cache_malformed_payload_even_without_a_degraded_reason(): void
    {
        Cache::flush();

        $state = (object) ['calls' => 0];

        $this->app->instance(SkyVisiblePlanetsService::class, new class($state) extends SkyVisiblePlanetsService {
            public function __construct(
                private readonly object $state
            ) {
            }

            public function fetch(float $lat, float $lon, string $tz): array
            {
                $this->state->calls++;

                return [
                    'sample_at' => '2026-02-27T20:45:00+01:00',
                    'sun_altitude_deg' => -19.5,
                ];
            }
        });

        $this->getJson('/api/sky/visible-planets?lat=48.1486&lon=17.1077&tz=Europe/Bratislava')->assertOk();
        $this->getJson('/api/sky/visible-planets?lat=48.1486&lon=17.1077&tz=Europe/Bratislava')->assertOk();

        $this->assertSame(2, $state->calls);
    }

    public function test_it_caches_visible_planets_payload(): void
    {
        Cache::flush();

        Http::fake([
            '*sky-summary*' => Http::sequence()
                ->push([
                    'moon' => null,
                    'sample_at' => '2026-02-27T21:00:00+01:00',
                    'sun_altitude_deg' => -21.0,
                    'planets' => [
                        [
                            'name' => 'Saturn',
                            'alt_max_deg' => 22.1,
                            'az_at_best_deg' => 134.4,
                            'elongation_deg' => 58.0,
                            'direction' => 'SE',
                            'best_from' => '21:00',
                            'best_to' => '01:20',
                        ],
                    ],
                ], 200)
                ->push([
                    'moon' => null,
                    'sample_at' => '2026-02-27T21:10:00+01:00',
                    'sun_altitude_deg' => -22.0,
                    'planets' => [
                        [
                            'name' => 'Mercury',
                            'alt_max_deg' => 11.0,
                            'az_at_best_deg' => 90.0,
                            'elongation_deg' => 21.5,
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

    public function test_it_prefers_jpl_horizons_payload_when_available(): void
    {
        Cache::flush();
        config()->set('observing.providers.jpl_horizons_url', 'https://horizons.test/api');

        Http::fake(function (\Illuminate\Http\Client\Request $request) {
            $url = $request->url();
            if (!str_starts_with($url, 'https://horizons.test/api')) {
                return Http::response(['message' => 'unexpected'], 500);
            }

            parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
            $command = (string) ($query['COMMAND'] ?? '');

            if ($command === "'10'") {
                return Http::response([
                    'result' => $this->horizonsResultLine(180.0, -18.2, -26.7, 0.99, 0.0, 0.0),
                ], 200);
            }

            $fixtures = [
                "'199'" => [95.2, 12.1, -0.5, 0.88, 10.1, 28.3],
                "'299'" => [112.4, 26.4, -3.9, 1.63, -4.3, 15.7],
                "'499'" => [140.0, 41.2, 1.2, 0.74, 5.4, 63.0],
                "'599'" => [172.1, 33.5, -2.1, 4.82, -12.3, 90.5],
                "'699'" => [205.7, 18.7, 0.8, 9.55, -2.2, 54.4],
            ];

            $line = $fixtures[$command] ?? null;
            if ($line === null) {
                return Http::response(['result' => ''], 200);
            }

            return Http::response([
                'result' => $this->horizonsResultLine($line[0], $line[1], $line[2], $line[3], $line[4], $line[5]),
            ], 200);
        });

        $response = $this->getJson('/api/sky/visible-planets?lat=48.1486&lon=17.1077&tz=Europe/Bratislava');

        $response->assertOk()
            ->assertJsonPath('source', 'jpl_horizons')
            ->assertJsonPath('sun_altitude_deg', -18.2)
            ->assertJsonCount(5, 'planets')
            ->assertJsonPath('planets.0.name', 'Mars');
    }

    public function test_it_enriches_jpl_payload_with_best_time_windows_from_microservice_when_available(): void
    {
        Cache::flush();
        config()->set('observing.providers.jpl_horizons_url', 'https://horizons.test/api');

        Http::fake(function (\Illuminate\Http\Client\Request $request) {
            $url = $request->url();

            if (str_contains($url, 'sky-summary')) {
                return Http::response([
                    'sample_at' => '2026-03-12T20:15:00+01:00',
                    'sun_altitude_deg' => -18.2,
                    'planets' => [
                        ['name' => 'Mercury', 'best_from' => '18:10', 'best_to' => '18:45', 'elongation_deg' => 28.3],
                        ['name' => 'Venus', 'best_from' => '18:20', 'best_to' => '19:10', 'elongation_deg' => 15.7],
                        ['name' => 'Mars', 'best_from' => '20:15', 'best_to' => '01:10', 'elongation_deg' => 63.0],
                        ['name' => 'Jupiter', 'best_from' => '19:40', 'best_to' => '23:10', 'elongation_deg' => 90.5],
                        ['name' => 'Saturn', 'best_from' => '18:30', 'best_to' => '21:00', 'elongation_deg' => 54.4],
                    ],
                ], 200);
            }

            if (!str_starts_with($url, 'https://horizons.test/api')) {
                return Http::response(['message' => 'unexpected'], 500);
            }

            parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
            $command = (string) ($query['COMMAND'] ?? '');

            if ($command === "'10'") {
                return Http::response([
                    'result' => $this->horizonsResultLine(180.0, -18.2, -26.7, 0.99, 0.0, 0.0),
                ], 200);
            }

            $fixtures = [
                "'199'" => [95.2, 12.1, -0.5, 0.88, 10.1, 28.3],
                "'299'" => [112.4, 26.4, -3.9, 1.63, -4.3, 15.7],
                "'499'" => [140.0, 41.2, 1.2, 0.74, 5.4, 63.0],
                "'599'" => [172.1, 33.5, -2.1, 4.82, -12.3, 90.5],
                "'699'" => [205.7, 18.7, 0.8, 9.55, -2.2, 54.4],
            ];

            $line = $fixtures[$command] ?? null;
            if ($line === null) {
                return Http::response(['result' => ''], 200);
            }

            return Http::response([
                'result' => $this->horizonsResultLine($line[0], $line[1], $line[2], $line[3], $line[4], $line[5]),
            ], 200);
        });

        $response = $this->getJson('/api/sky/visible-planets?lat=48.1486&lon=17.1077&tz=Europe/Bratislava');

        $response->assertOk()
            ->assertJsonPath('source', 'jpl_horizons')
            ->assertJsonCount(5, 'planets');

        $rowsByName = collect($response->json('planets'))
            ->filter(fn ($row): bool => is_array($row) && isset($row['name']))
            ->keyBy(fn (array $row): string => strtolower((string) $row['name']));

        $this->assertSame('20:15-01:10', $rowsByName->get('mars')['best_time_window'] ?? null);
        $this->assertSame('19:40-23:10', $rowsByName->get('jupiter')['best_time_window'] ?? null);
        $this->assertSame('18:20-19:10', $rowsByName->get('venusa')['best_time_window'] ?? null);
        $this->assertSame('18:10-18:45', $rowsByName->get('merkur')['best_time_window'] ?? null);
    }

    private function horizonsResultLine(
        float $azimuth,
        float $altitude,
        float $magnitude,
        float $distanceAu,
        float $radialVelocity,
        float $elongation
    ): string {
        $line = sprintf(
            ' 2026-Mar-12 21:00:00.000 00 29 45.51 +02 00 26.0 %9.6f %9.6f %7.3f 0.852 %1.14f %10.7f %8.4f /T',
            $azimuth,
            $altitude,
            $magnitude,
            $distanceAu,
            $radialVelocity,
            $elongation
        );

        return '$$SOE' . PHP_EOL . $line . PHP_EOL . '$$EOE';
    }
}
