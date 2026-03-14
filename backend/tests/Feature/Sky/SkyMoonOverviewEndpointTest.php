<?php

namespace Tests\Feature\Sky;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SkyMoonOverviewEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_moon_overview_with_position_distance_and_upcoming_times(): void
    {
        Cache::flush();

        Http::fake(function ($request) {
            $url = $request->url();

            if (str_contains($url, '/api/rstt/oneday')) {
                return Http::response([
                    'properties' => [
                        'data' => [
                            'curphase' => 'Waning Crescent',
                            'fracillum' => '31%',
                            'moondata' => [
                                ['phen' => 'Rise', 'time' => '03:42'],
                                ['phen' => 'Upper Transit', 'time' => '07:27'],
                                ['phen' => 'Set', 'time' => '11:17'],
                            ],
                            'sundata' => [],
                        ],
                    ],
                ], 200);
            }

            if (str_contains($url, '/api/moon/phases/year')) {
                $query = [];
                parse_str(parse_url($url, PHP_URL_QUERY) ?: '', $query);
                $year = (int) ($query['year'] ?? 0);

                if ($year !== 2026) {
                    return Http::response(['phasedata' => []], 200);
                }

                return Http::response([
                    'phasedata' => [
                        ['year' => 2026, 'month' => 3, 'day' => 11, 'phase' => 'Last Quarter', 'time' => '10:38'],
                        ['year' => 2026, 'month' => 3, 'day' => 19, 'phase' => 'New Moon', 'time' => '02:23'],
                        ['year' => 2026, 'month' => 3, 'day' => 25, 'phase' => 'First Quarter', 'time' => '20:17'],
                        ['year' => 2026, 'month' => 4, 'day' => 2, 'phase' => 'Full Moon', 'time' => '04:11'],
                    ],
                ], 200);
            }

            if (str_contains($url, '/api/horizons.api')) {
                return Http::response([
                    'result' => implode("\n", [
                        '$$SOE',
                        ' 2026-Mar-13 00:00:00.000 * - - - - - 349.74 -67.94 1.00 2.00 0.00266000000000 0.0000000 10.0',
                        '$$EOE',
                    ]),
                ], 200);
            }

            return Http::response(['message' => 'not found'], 404);
        });

        $response = $this->getJson('/api/sky/moon-overview?lat=48.1486&lon=17.1077&tz=Europe/Bratislava&date=2026-03-13');

        $response
            ->assertOk()
            ->assertJsonPath('timezone', 'Europe/Bratislava')
            ->assertJsonPath('moon_phase', 'waning_crescent')
            ->assertJsonPath('moon_illumination_percent', 31)
            ->assertJsonPath('moon_azimuth_deg', 349.74)
            ->assertJsonPath('moon_altitude_deg', -67.94)
            ->assertJsonPath('moon_direction', 'N')
            ->assertJsonPath('moon_distance_km', 397930)
            ->assertJsonPath('next_new_moon_at', '2026-03-19T03:23:00+01:00')
            ->assertJsonPath('next_full_moon_at', '2026-04-02T06:11:00+02:00')
            ->assertJsonPath('next_moonrise_at', '2026-03-13T03:42:00+01:00')
            ->assertJsonPath('source.phase.provider', 'USNO')
            ->assertJsonPath('source.position.provider', 'JPL')
            ->assertJsonPath('source.next_phases.provider', 'USNO');
    }
}

