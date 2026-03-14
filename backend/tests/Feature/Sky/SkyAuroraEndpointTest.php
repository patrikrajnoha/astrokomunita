<?php

namespace Tests\Feature\Sky;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SkyAuroraEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_aurora_watch_payload_shape(): void
    {
        Cache::flush();
        config()->set('observing.providers.swpc_aurora_latest_url', 'https://swpc.test/ovation_aurora_latest.json');

        Http::fake([
            'https://swpc.test/ovation_aurora_latest.json' => Http::response([
                'Observation Time' => '2026-03-14T21:12:00Z',
                'Forecast Time' => '2026-03-14T21:52:00Z',
                'Data Format' => '[Longitude, Latitude, Aurora]',
                'coordinates' => [
                    [17, 48, 4],
                    [17, 54, 38],
                    [18, 60, 72],
                ],
            ], 200),
        ]);

        $response = $this->getJson('/api/sky/aurora?lat=48.1486&lon=17.1077&tz=Europe/Bratislava');

        $response->assertOk()
            ->assertJsonStructure([
                'available',
                'watch_score',
                'watch_label',
                'corridor_peak_score',
                'nearest_score',
                'forecast_for',
                'observed_at',
                'updated_at',
                'data_format',
                'inference',
                'source' => ['provider', 'label', 'url'],
                'sources' => ['aurora'],
            ])
            ->assertJsonPath('available', true)
            ->assertJsonPath('watch_score', 72)
            ->assertJsonPath('watch_label', 'Vysoka sanca')
            ->assertJsonPath('source.label', 'NOAA SWPC OVATION');
    }

    public function test_it_caches_aurora_watch_payload(): void
    {
        Cache::flush();
        config()->set('observing.providers.swpc_aurora_latest_url', 'https://swpc.test/ovation_aurora_latest.json');

        Http::fake([
            'https://swpc.test/ovation_aurora_latest.json' => Http::sequence()
                ->push([
                    'Observation Time' => '2026-03-14T21:12:00Z',
                    'Forecast Time' => '2026-03-14T21:52:00Z',
                    'Data Format' => '[Longitude, Latitude, Aurora]',
                    'coordinates' => [[17, 48, 12], [17, 56, 28]],
                ], 200)
                ->push([
                    'Observation Time' => '2026-03-14T21:17:00Z',
                    'Forecast Time' => '2026-03-14T21:57:00Z',
                    'Data Format' => '[Longitude, Latitude, Aurora]',
                    'coordinates' => [[17, 48, 66], [17, 56, 80]],
                ], 200),
        ]);

        $first = $this->getJson('/api/sky/aurora?lat=48.1486&lon=17.1077&tz=Europe/Bratislava')->assertOk();
        $second = $this->getJson('/api/sky/aurora?lat=48.1486&lon=17.1077&tz=Europe/Bratislava')->assertOk();

        $this->assertSame($first->json('watch_score'), $second->json('watch_score'));
        $this->assertSame($first->json('forecast_for'), $second->json('forecast_for'));
        Http::assertSentCount(1);
    }
}
