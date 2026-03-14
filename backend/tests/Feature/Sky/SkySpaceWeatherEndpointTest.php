<?php

namespace Tests\Feature\Sky;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SkySpaceWeatherEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_space_weather_payload_shape_with_aurora_watch_summary(): void
    {
        Cache::flush();
        config()->set('observing.providers.swpc_planetary_k_index_url', 'https://swpc.test/planetary_k_index_1m.json');
        config()->set('observing.providers.swpc_aurora_latest_url', 'https://swpc.test/ovation_aurora_latest.json');

        Http::fake([
            'https://swpc.test/planetary_k_index_1m.json' => Http::response([
                ['time_tag' => '2026-03-14T21:15:00', 'kp_index' => 5, 'estimated_kp' => 5.33],
                ['time_tag' => '2026-03-14T21:16:00', 'kp_index' => 6, 'estimated_kp' => 6.33],
            ], 200),
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

        $response = $this->getJson('/api/sky/space-weather?lat=48.1486&lon=17.1077&tz=Europe/Bratislava');

        $response->assertOk()
            ->assertJsonStructure([
                'available',
                'kp_index',
                'estimated_kp',
                'geomagnetic_level',
                'noaa_scale',
                'updated_at',
                'observed_at',
                'aurora' => [
                    'available',
                    'watch_score',
                    'watch_label',
                    'corridor_peak_score',
                    'nearest_score',
                    'forecast_for',
                    'observed_at',
                    'inference',
                ],
                'source' => ['provider', 'label', 'url'],
                'sources' => ['kp', 'aurora'],
            ])
            ->assertJsonPath('available', true)
            ->assertJsonPath('kp_index', 6)
            ->assertJsonPath('noaa_scale', 'G2')
            ->assertJsonPath('aurora.watch_score', 72)
            ->assertJsonPath('aurora.watch_label', 'Vysoka sanca')
            ->assertJsonPath('source.label', 'NOAA SWPC');
    }

    public function test_it_caches_space_weather_payload(): void
    {
        Cache::flush();
        config()->set('observing.providers.swpc_planetary_k_index_url', 'https://swpc.test/planetary_k_index_1m.json');
        config()->set('observing.providers.swpc_aurora_latest_url', 'https://swpc.test/ovation_aurora_latest.json');

        Http::fake([
            'https://swpc.test/planetary_k_index_1m.json' => Http::sequence()
                ->push([['time_tag' => '2026-03-14T21:16:00', 'kp_index' => 4, 'estimated_kp' => 4.33]], 200)
                ->push([['time_tag' => '2026-03-14T21:17:00', 'kp_index' => 7, 'estimated_kp' => 7.33]], 200),
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

        $first = $this->getJson('/api/sky/space-weather?lat=48.1486&lon=17.1077&tz=Europe/Bratislava')->assertOk();
        $second = $this->getJson('/api/sky/space-weather?lat=48.1486&lon=17.1077&tz=Europe/Bratislava')->assertOk();

        $this->assertSame($first->json('kp_index'), $second->json('kp_index'));
        $this->assertSame($first->json('aurora.watch_score'), $second->json('aurora.watch_score'));
        Http::assertSentCount(2);
    }
}
