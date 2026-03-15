<?php

namespace Tests\Feature\Sky;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SkyEphemerisEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_jpl_ephemerides_for_planets_comets_and_asteroids(): void
    {
        Cache::flush();
        config()->set('observing.providers.jpl_horizons_url', 'https://horizons.test/api');
        config()->set('observing.providers.jpl_sbdd_url', 'https://sbddb.test/query');

        Http::fake(function (Request $request) {
            $url = $request->url();

            if (str_starts_with($url, 'https://horizons.test/api')) {
                parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
                $command = (string) ($query['COMMAND'] ?? '');

                if ($command === "'10'") {
                    return Http::response(['result' => $this->horizonsResultLine(180.0, -19.4, -26.7, 0.99, 0.0, 0.0)], 200);
                }

                $fixtures = [
                    "'199'" => [85.5, 11.4, -0.2, 0.71, -4.0, 18.2],
                    "'299'" => [103.3, 25.7, -3.8, 1.52, -2.2, 14.3],
                    "'499'" => [140.1, 42.0, 1.3, 0.88, 8.1, 68.9],
                    "'599'" => [171.5, 31.2, -2.0, 4.71, -11.2, 86.2],
                    "'699'" => [208.8, 17.4, 0.7, 9.32, -2.4, 50.8],
                ];

                $line = $fixtures[$command] ?? null;
                if ($line === null) {
                    return Http::response(['result' => '$$SOE' . PHP_EOL . '$$EOE'], 200);
                }

                return Http::response([
                    'result' => $this->horizonsResultLine($line[0], $line[1], $line[2], $line[3], $line[4], $line[5]),
                ], 200);
            }

            if (str_starts_with($url, 'https://sbddb.test/query')) {
                parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
                $kind = (string) ($query['sb-kind'] ?? '');

                if ($kind === 'c') {
                    return Http::response([
                        'fields' => ['full_name', 'pdes', 'kind', 'neo', 'pha', 'e', 'a', 'q', 'i', 'om', 'w', 'tp', 'moid'],
                        'data' => [
                            ['1P/Halley', '1P', 'cn', 'Y', null, '0.9679', '17.93', '0.575', '162.19', '59.10', '112.24', '2446469.97', '0.0745'],
                        ],
                    ], 200);
                }

                return Http::response([
                    'fields' => ['full_name', 'pdes', 'kind', 'neo', 'pha', 'e', 'a', 'q', 'i', 'om', 'w', 'tp', 'moid'],
                    'data' => [
                        ['1 Ceres', '1', 'an', 'N', 'N', '0.0796', '2.766', '2.546', '10.59', '80.25', '73.30', '2461599.95', '1.58'],
                    ],
                ], 200);
            }

            return Http::response(['message' => 'unexpected'], 500);
        });

        $response = $this->getJson('/api/sky/ephemeris?lat=48.1486&lon=17.1077&tz=Europe/Bratislava');

        $response->assertOk()
            ->assertJsonPath('source.planets', 'jpl_horizons')
            ->assertJsonPath('source.small_bodies', 'jpl_sbddb')
            ->assertJsonPath('sun_altitude_deg', -19.4)
            ->assertJsonCount(5, 'planets')
            ->assertJsonPath('comets.0.name', '1P/Halley')
            ->assertJsonPath('asteroids.0.name', '1 Ceres');
    }

    public function test_it_caches_ephemeris_payload(): void
    {
        Cache::flush();
        config()->set('observing.providers.jpl_horizons_url', 'https://horizons.test/api');
        config()->set('observing.providers.jpl_sbdd_url', 'https://sbddb.test/query');

        Http::fake(function (Request $request) {
            $url = $request->url();

            if (str_starts_with($url, 'https://horizons.test/api')) {
                parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
                $command = (string) ($query['COMMAND'] ?? '');

                if ($command === "'10'") {
                    return Http::response(['result' => $this->horizonsResultLine(180.0, -20.0, -26.7, 0.99, 0.0, 0.0)], 200);
                }

                return Http::response(['result' => $this->horizonsResultLine(150.0, 30.0, 0.0, 1.0, 0.0, 50.0)], 200);
            }

            if (str_starts_with($url, 'https://sbddb.test/query')) {
                return Http::response([
                    'fields' => ['full_name', 'pdes', 'kind', 'neo', 'pha', 'e', 'a', 'q', 'i', 'om', 'w', 'tp', 'moid'],
                    'data' => [
                        ['1 Sample', '1', 'an', 'N', 'N', '0.1', '2.1', '1.9', '5.0', '80.0', '60.0', '2461599.95', '0.5'],
                    ],
                ], 200);
            }

            return Http::response(['message' => 'unexpected'], 500);
        });

        $first = $this->getJson('/api/sky/ephemeris?lat=48.1486&lon=17.1077&tz=Europe/Bratislava')->assertOk();
        $second = $this->getJson('/api/sky/ephemeris?lat=48.1486&lon=17.1077&tz=Europe/Bratislava')->assertOk();

        $this->assertSame($first->json('sample_at'), $second->json('sample_at'));
        Http::assertSentCount(8);
    }

    public function test_it_parses_horizons_rows_with_presence_flags_in_ephemeris_payload(): void
    {
        Cache::flush();
        config()->set('observing.providers.jpl_horizons_url', 'https://horizons.test/api');
        config()->set('observing.providers.jpl_sbdd_url', 'https://sbddb.test/query');

        Http::fake(function (Request $request) {
            $url = $request->url();

            if (str_starts_with($url, 'https://horizons.test/api')) {
                parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
                $command = (string) ($query['COMMAND'] ?? '');

                if ($command === "'10'") {
                    return Http::response(['result' => $this->horizonsResultLine(180.0, -19.4, -26.7, 0.99, 0.0, 0.0, '*m')], 200);
                }

                $fixtures = [
                    "'199'" => [85.5, 11.4, -0.2, 0.71, -4.0, 18.2],
                    "'299'" => [103.3, 25.7, -3.8, 1.52, -2.2, 14.3],
                    "'499'" => [140.1, 42.0, 1.3, 0.88, 8.1, 68.9],
                    "'599'" => [171.5, 31.2, -2.0, 4.71, -11.2, 86.2],
                    "'699'" => [208.8, 17.4, 0.7, 9.32, -2.4, 50.8],
                ];

                $line = $fixtures[$command] ?? null;
                if ($line === null) {
                    return Http::response(['result' => '$$SOE' . PHP_EOL . '$$EOE'], 200);
                }

                return Http::response([
                    'result' => $this->horizonsResultLine($line[0], $line[1], $line[2], $line[3], $line[4], $line[5], '*m'),
                ], 200);
            }

            if (str_starts_with($url, 'https://sbddb.test/query')) {
                return Http::response([
                    'fields' => ['full_name', 'pdes', 'kind', 'neo', 'pha', 'e', 'a', 'q', 'i', 'om', 'w', 'tp', 'moid'],
                    'data' => [
                        ['1 Sample', '1', 'an', 'N', 'N', '0.1', '2.1', '1.9', '5.0', '80.0', '60.0', '2461599.95', '0.5'],
                    ],
                ], 200);
            }

            return Http::response(['message' => 'unexpected'], 500);
        });

        $response = $this->getJson('/api/sky/ephemeris?lat=48.1486&lon=17.1077&tz=Europe/Bratislava');

        $response->assertOk()
            ->assertJsonPath('sun_altitude_deg', -19.4)
            ->assertJsonPath('planets.0.name', 'Mars')
            ->assertJsonPath('planets.0.altitude_deg', 42)
            ->assertJsonPath('planets.1.name', 'Jupiter')
            ->assertJsonPath('planets.1.altitude_deg', 31.2);
    }

    private function horizonsResultLine(
        float $azimuth,
        float $altitude,
        float $magnitude,
        float $distanceAu,
        float $radialVelocity,
        float $elongation,
        string $flags = ''
    ): string {
        $flagsSegment = trim($flags) !== '' ? ' ' . trim($flags) : '';
        $line = sprintf(
            ' 2026-Mar-12 21:00:00.000%s 00 29 45.51 +02 00 26.0 %9.6f %9.6f %7.3f 0.852 %1.14f %10.7f %8.4f /T',
            $flagsSegment,
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
