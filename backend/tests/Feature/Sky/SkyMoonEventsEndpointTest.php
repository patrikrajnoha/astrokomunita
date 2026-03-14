<?php

namespace Tests\Feature\Sky;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SkyMoonEventsEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_special_moon_events_and_uses_cache(): void
    {
        Cache::flush();

        Http::fake(function ($request) {
            $url = $request->url();

            if (str_contains($url, '/api/moon/phases/year')) {
                $query = [];
                parse_str(parse_url($url, PHP_URL_QUERY) ?: '', $query);
                $year = (int) ($query['year'] ?? 0);

                if ($year !== 2026) {
                    return Http::response(['phasedata' => []], 200);
                }

                return Http::response([
                    'phasedata' => [
                        ['year' => 2026, 'month' => 1, 'day' => 1, 'phase' => 'New Moon', 'time' => '01:00'],
                        ['year' => 2026, 'month' => 1, 'day' => 14, 'phase' => 'Full Moon', 'time' => '23:00'],
                        ['year' => 2026, 'month' => 1, 'day' => 30, 'phase' => 'New Moon', 'time' => '13:00'],
                        ['year' => 2026, 'month' => 2, 'day' => 13, 'phase' => 'Full Moon', 'time' => '21:00'],
                        ['year' => 2026, 'month' => 2, 'day' => 28, 'phase' => 'New Moon', 'time' => '09:00'],
                        ['year' => 2026, 'month' => 3, 'day' => 14, 'phase' => 'Full Moon', 'time' => '17:00'],
                        ['year' => 2026, 'month' => 3, 'day' => 31, 'phase' => 'Full Moon', 'time' => '06:00'],
                        ['year' => 2026, 'month' => 4, 'day' => 13, 'phase' => 'New Moon', 'time' => '05:00'],
                    ],
                ], 200);
            }

            if (str_contains($url, '/api/horizons.api')) {
                $query = [];
                parse_str(parse_url($url, PHP_URL_QUERY) ?: '', $query);
                $timeKey = trim((string) ($query['TLIST'] ?? ''), "'");

                $distanceByTime = [
                    '2026-01-01 01:00' => '0.00240000000000',
                    '2026-01-14 23:00' => '0.00251000000000',
                    '2026-01-30 13:00' => '0.00243000000000',
                    '2026-02-13 21:00' => '0.00282000000000',
                    '2026-02-28 09:00' => '0.00272000000000',
                    '2026-03-14 17:00' => '0.00306000000000',
                    '2026-03-31 06:00' => '0.00321000000000',
                    '2026-04-13 05:00' => '0.00283000000000',
                ];

                $delta = $distanceByTime[$timeKey] ?? '0.00290000000000';

                return Http::response([
                    'result' => implode("\n", [
                        '$$SOE',
                        sprintf(' 2026-01-01 00:00:00.000   %s   0.0000000', $delta),
                        '$$EOE',
                    ]),
                ], 200);
            }

            return Http::response(['message' => 'not found'], 404);
        });

        $first = $this->getJson('/api/sky/moon-events?year=2026&tz=Europe/Bratislava');
        $second = $this->getJson('/api/sky/moon-events?year=2026&tz=Europe/Bratislava');

        $first
            ->assertOk()
            ->assertJsonPath('year', 2026)
            ->assertJsonPath('timezone', 'Europe/Bratislava')
            ->assertJsonPath('source.moon_phases.provider', 'USNO')
            ->assertJsonPath('source.distance.provider', 'JPL')
            ->assertJsonPath('source.moon_phases.api_key_required', false)
            ->assertJsonPath('source.distance.api_key_required', false)
            ->assertJsonPath('events.0.key', 'super_new_moon');

        $keys = array_map(
            static fn (array $row): string => (string) ($row['key'] ?? ''),
            is_array($first->json('events')) ? $first->json('events') : []
        );

        $this->assertContains('blue_moon', $keys);
        $this->assertContains('black_moon', $keys);
        $this->assertContains('super_full_moon', $keys);
        $this->assertContains('micro_full_moon', $keys);
        $this->assertNotContains('no_black_moon', $keys);

        $this->assertSame($first->json('events'), $second->json('events'));
    }
}

