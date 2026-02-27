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

        Http::fake([
            'https://aa.usno.navy.mil/*' => Http::response($this->usnoPayload('Waning Crescent', '78%'), 200),
            'http://127.0.0.1:8010/sky-summary*' => Http::response([
                'moon' => [
                    'rise_local' => '18:42',
                    'set_local' => '06:11',
                ],
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
                'moonrise_at',
                'moonset_at',
            ])
            ->assertJsonPath('moon_phase', 'waning_crescent')
            ->assertJsonPath('moon_illumination_percent', 78);

        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/', (string) $response->json('sunrise_at'));
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/', (string) $response->json('sunset_at'));
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/', (string) $response->json('moonrise_at'));
    }

    public function test_it_caches_astronomy_payload_and_falls_back_from_invalid_query_timezone_to_user_timezone(): void
    {
        Cache::flush();

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
            'http://127.0.0.1:8010/sky-summary*' => Http::sequence()
                ->push(['moon' => ['rise_local' => '17:00', 'set_local' => '05:30'], 'planets' => []], 200)
                ->push(['moon' => ['rise_local' => '22:00', 'set_local' => '10:00'], 'planets' => []], 200),
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
