<?php

namespace Tests\Feature\Sky;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SkyAstronomyEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_astronomy_payload_with_iso8601_offset_times(): void
    {
        Cache::flush();
        config()->set('observing.sky_summary.microservice_base', 'http://sky.test');

        Http::fake([
            'https://aa.usno.navy.mil/*' => Http::response($this->usnoPayload('Waning Crescent', '78%'), 200),
            'http://sky.test/sky-summary*' => Http::response([
                'moon' => [
                    'rise_local' => '',
                    'set_local' => '',
                ],
                'sample_at' => '2026-02-27T00:00:00+01:00',
                'sun_altitude_deg' => -42.4,
                'planets' => [],
            ], 200),
        ]);

        $response = $this->getJson('/api/sky/astronomy?lat=48.1486&lon=17.1077&tz=Europe/Bratislava');

        $response->assertOk()
            ->assertJsonStructure([
                'moon_phase',
                'moon_illumination_percent',
                'sunrise_at',
                'sunset_at',
                'sun_altitude_deg',
                'moon_altitude_deg',
                'sample_at',
                'moonrise_at',
                'moonset_at',
            ])
            ->assertJsonPath('moon_phase', 'waning_crescent')
            ->assertJsonPath('moon_illumination_percent', 78)
            ->assertJsonPath('sun_altitude_deg', -42.4)
            ->assertJsonPath('moon_altitude_deg', null)
            ->assertJsonPath('sample_at', '2026-02-27T00:00:00+01:00');

        $this->assertNullableIso8601($response->json('sunrise_at'));
        $this->assertNullableIso8601($response->json('sunset_at'));
        $this->assertNull($response->json('moonrise_at'));
        $this->assertNull($response->json('moonset_at'));
    }

    public function test_it_caches_astronomy_payload_and_falls_back_from_invalid_query_timezone_to_user_timezone(): void
    {
        Cache::flush();
        config()->set('observing.sky_summary.microservice_base', 'http://sky.test');

        $user = User::factory()->create([
            'latitude' => 48.7164,
            'longitude' => 21.2611,
            'timezone' => 'Europe/Prague',
            'location_label' => 'Kosice',
            'location_source' => 'manual',
        ]);

        Http::fake([
            'https://aa.usno.navy.mil/*' => Http::sequence()
                ->push($this->usnoPayload('Full Moon', '99%'), 200)
                ->push($this->usnoPayload('New Moon', '1%'), 200),
            'http://sky.test/sky-summary*' => Http::sequence()
                ->push([
                    'moon' => ['rise_local' => '17:00', 'set_local' => '05:30'],
                    'sample_at' => '2026-02-27T00:00:00+01:00',
                    'sun_altitude_deg' => -33.1,
                    'planets' => [],
                ], 200)
                ->push([
                    'moon' => ['rise_local' => '22:00', 'set_local' => '10:00'],
                    'sample_at' => '2026-02-27T00:10:00+01:00',
                    'sun_altitude_deg' => -32.9,
                    'planets' => [],
                ], 200),
        ]);

        $first = $this->actingAs($user)->getJson('/api/sky/astronomy?tz=Invalid/Timezone')->assertOk();
        $second = $this->actingAs($user)->getJson('/api/sky/astronomy?tz=Invalid/Timezone')->assertOk();

        $this->assertSame($first->json('moon_phase'), $second->json('moon_phase'));
        $this->assertSame($first->json('sunrise_at'), $second->json('sunrise_at'));
        Http::assertSentCount(2);

        Http::assertSent(function ($request) {
            if (!str_contains($request->url(), '/sky-summary')) {
                return false;
            }

            parse_str(parse_url($request->url(), PHP_URL_QUERY) ?: '', $query);
            return ($query['tz'] ?? null) === 'Europe/Prague';
        });
    }

    public function test_it_maps_current_moon_altitude_from_sky_microservice_hourly_payload(): void
    {
        Cache::flush();
        config()->set('observing.sky_summary.microservice_base', 'http://sky.test');

        Http::fake([
            'https://aa.usno.navy.mil/*' => Http::response($this->usnoPayload('Waxing Crescent', '25%'), 200),
            'http://sky.test/sky-summary*' => Http::response([
                'moon' => [
                    'rise_local' => '10:10',
                    'set_local' => '23:59',
                    'altitude_hourly' => [
                        ['local_time' => '19:00', 'altitude_deg' => 5.1],
                        ['local_time' => '20:00', 'altitude_deg' => 12.4],
                        ['local_time' => '21:00', 'altitude_deg' => 20.0],
                    ],
                ],
                'sample_at' => '2026-02-27T20:05:00+01:00',
                'sun_altitude_deg' => -20.1,
                'planets' => [],
            ], 200),
        ]);

        $this->getJson('/api/sky/astronomy?lat=48.1486&lon=17.1077&tz=Europe/Bratislava')
            ->assertOk()
            ->assertJsonPath('moon_altitude_deg', 12.4);
    }

    public function test_it_returns_degraded_payload_instead_of_503_when_upstream_is_unavailable(): void
    {
        Cache::flush();
        config()->set('observing.sky_summary.microservice_base', 'http://sky.test');

        Http::fake([
            'https://aa.usno.navy.mil/*' => Http::response(['message' => 'offline'], 503),
            'http://sky.test/sky-summary*' => Http::response(['message' => 'offline'], 503),
        ]);

        $response = $this->getJson('/api/sky/astronomy?lat=48.1486&lon=17.1077&tz=Europe/Bratislava');

        $response->assertOk()
            ->assertJsonPath('moon_phase', 'unknown')
            ->assertJsonPath('moon_illumination_percent', null)
            ->assertJsonPath('sunrise_at', null)
            ->assertJsonPath('sunset_at', null)
            ->assertJsonPath('civil_twilight_end_at', null)
            ->assertJsonPath('sun_altitude_deg', null)
            ->assertJsonPath('moon_altitude_deg', null)
            ->assertJsonPath('sample_at', null)
            ->assertJsonPath('moonrise_at', null)
            ->assertJsonPath('moonset_at', null);
    }

    private function assertNullableIso8601(mixed $value): void
    {
        if ($value === null) {
            $this->assertNull($value);

            return;
        }

        $this->assertIsString($value);
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/',
            $value
        );
    }

    private function usnoPayload(string $phase, string $fracillum): array
    {
        return [
            'properties' => [
                'data' => [
                    'curphase' => $phase,
                    'fracillum' => $fracillum,
                    'sundata' => [
                        ['phen' => 'Rise', 'time' => '07:07'],
                        ['phen' => 'Set', 'time' => '17:05'],
                    ],
                ],
            ],
        ];
    }
}
