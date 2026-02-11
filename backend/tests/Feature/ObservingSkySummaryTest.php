<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ObservingSkySummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_sky_summary_payload_and_active_meteor_data(): void
    {
        config()->set('observing.sky_summary.microservice_base', 'http://127.0.0.1:8010');
        config()->set('observing.sky_summary.endpoint_path', '/sky-summary');

        Http::fake([
            'http://127.0.0.1:8010/sky-summary*' => Http::response([
                'moon' => [
                    'phase_deg' => 149.4,
                    'phase_name' => 'Waxing gibbous',
                    'illumination' => 92.9,
                    'rise_local' => '18:42',
                    'set_local' => '06:11',
                ],
                'planets' => [
                    [
                        'key' => 'jupiter',
                        'name' => 'Jupiter',
                        'best_from' => '19:40',
                        'best_to' => '23:10',
                        'direction' => 'SE',
                        'alt_max_deg' => 38.2,
                        'az_at_best_deg' => 145.0,
                        'is_low' => false,
                    ],
                ],
            ], 200),
        ]);

        $response = $this->getJson('/api/observing/sky-summary?lat=48.1486&lon=17.1077&date=2026-08-12&tz=Europe/Bratislava');

        $response->assertOk();
        $response->assertJsonStructure([
            'meta' => ['lat', 'lon', 'tz', 'date', 'generated_at'],
            'moon' => ['phase_deg', 'phase_name', 'illumination', 'rise_local', 'set_local'],
            'planets',
            'meteors',
            'comets',
        ]);

        $response->assertJsonPath('moon.phase_name', 'Waxing gibbous');
        $response->assertJsonPath('planets.0.key', 'jupiter');
        $response->assertJsonFragment([
            'id' => 'perseids',
            'active_today' => true,
            'peak_date' => '08-12',
            'peak_in_days' => 0,
        ]);

        Http::assertSent(function ($request) {
            parse_str(parse_url($request->url(), PHP_URL_QUERY) ?: '', $query);

            return ($query['tz'] ?? null) === 'Europe/Bratislava';
        });
    }

    public function test_it_falls_back_when_microservice_is_unavailable(): void
    {
        config()->set('observing.sky_summary.microservice_base', 'http://127.0.0.1:8010');
        config()->set('observing.sky_summary.endpoint_path', '/sky-summary');

        Http::fake([
            'http://127.0.0.1:8010/sky-summary*' => Http::response(['message' => 'Service unavailable'], 503),
        ]);

        $response = $this->getJson('/api/observing/sky-summary?lat=48.1486&lon=17.1077&date=2026-08-12&tz=Europe/Bratislava');

        $response->assertOk();
        $response->assertJsonPath('moon', null);
        $response->assertJsonPath('planets', []);
        $response->assertJsonPath('meta.date', '2026-08-12');
        $this->assertIsString($response->json('meta.error'));
    }

    public function test_it_falls_back_to_app_timezone_when_timezone_is_invalid(): void
    {
        config()->set('app.timezone', 'UTC');
        config()->set('observing.sky_summary.microservice_base', 'http://127.0.0.1:8010');
        config()->set('observing.sky_summary.endpoint_path', '/sky-summary');

        Http::fake([
            'http://127.0.0.1:8010/sky-summary*' => Http::response([
                'moon' => null,
                'planets' => [],
            ], 200),
        ]);

        $response = $this->getJson('/api/observing/sky-summary?lat=48.1486&lon=17.1077&date=2026-08-12&tz=Euro');

        $response->assertOk();
        $response->assertJsonPath('meta.tz', 'UTC');
        $this->assertIsString($response->json('meta.warning'));

        Http::assertSent(function ($request) {
            parse_str(parse_url($request->url(), PHP_URL_QUERY) ?: '', $query);

            return ($query['tz'] ?? null) === 'UTC';
        });
    }
}
