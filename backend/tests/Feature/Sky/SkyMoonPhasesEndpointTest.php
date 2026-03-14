<?php

namespace Tests\Feature\Sky;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SkyMoonPhasesEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_all_moon_phases_with_current_phase_highlighted_and_uses_cache(): void
    {
        Cache::flush();

        Http::fake(function ($request) {
            if (str_contains($request->url(), '/api/rstt/oneday')) {
                return Http::response([
                    'properties' => [
                        'data' => [
                            'curphase' => 'Waning Crescent',
                            'fracillum' => '40%',
                            'sundata' => [],
                        ],
                    ],
                ], 200);
            }

            if (!str_contains($request->url(), '/api/moon/phases/year')) {
                return Http::response(['message' => 'not found'], 404);
            }

            $query = [];
            parse_str(parse_url($request->url(), PHP_URL_QUERY) ?: '', $query);
            $year = (int) ($query['year'] ?? 0);

            if ($year !== 2026) {
                return Http::response(['phasedata' => []], 200);
            }

            return Http::response([
                'phasedata' => [
                    ['year' => 2026, 'month' => 2, 'day' => 17, 'phase' => 'New Moon', 'time' => '10:00'],
                    ['year' => 2026, 'month' => 2, 'day' => 24, 'phase' => 'First Quarter', 'time' => '12:00'],
                    ['year' => 2026, 'month' => 3, 'day' => 3, 'phase' => 'Full Moon', 'time' => '14:00'],
                    ['year' => 2026, 'month' => 3, 'day' => 10, 'phase' => 'Last Quarter', 'time' => '16:00'],
                    ['year' => 2026, 'month' => 3, 'day' => 17, 'phase' => 'New Moon', 'time' => '18:00'],
                    ['year' => 2026, 'month' => 3, 'day' => 25, 'phase' => 'First Quarter', 'time' => '20:00'],
                    ['year' => 2026, 'month' => 4, 'day' => 2, 'phase' => 'Full Moon', 'time' => '04:00'],
                ],
            ], 200);
        });

        $first = $this->getJson('/api/sky/moon-phases?date=2026-03-12&tz=Europe/Bratislava');
        $second = $this->getJson('/api/sky/moon-phases?date=2026-03-12&tz=Europe/Bratislava');

        $first
            ->assertOk()
            ->assertJsonPath('reference_date', '2026-03-12')
            ->assertJsonPath('timezone', 'Europe/Bratislava')
            ->assertJsonPath('current_phase', 'waning_crescent')
            ->assertJsonPath('source.provider', 'USNO')
            ->assertJsonPath('source.api_key_required', false)
            ->assertJsonCount(8, 'phases')
            ->assertJsonPath('phases.0.key', 'new_moon')
            ->assertJsonPath('phases.1.key', 'waxing_crescent')
            ->assertJsonPath('phases.2.key', 'first_quarter')
            ->assertJsonPath('phases.3.key', 'waxing_gibbous')
            ->assertJsonPath('phases.4.key', 'full_moon')
            ->assertJsonPath('phases.5.key', 'waning_gibbous')
            ->assertJsonPath('phases.6.key', 'last_quarter')
            ->assertJsonPath('phases.7.key', 'waning_crescent')
            ->assertJsonCount(4, 'major_events')
            ->assertJsonPath('major_events.0.key', 'last_quarter')
            ->assertJsonPath('major_events.1.key', 'new_moon')
            ->assertJsonPath('major_events.2.key', 'first_quarter')
            ->assertJsonPath('major_events.3.key', 'full_moon')
            ->assertJsonPath('phases.7.is_current', true);

        $this->assertSame($first->json('current_phase'), $second->json('current_phase'));
        $this->assertSame($first->json('phases.7.start_date'), $second->json('phases.7.start_date'));
        Http::assertSentCount(4);
    }
}
