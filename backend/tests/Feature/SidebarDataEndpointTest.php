<?php

namespace Tests\Feature;

use App\Models\BlogPost;
use App\Models\Event;
use App\Models\User;
use App\Services\TranslationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SidebarDataEndpointTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Cache::flush();
        parent::tearDown();
    }

    public function test_it_returns_requested_sidebar_widget_payloads_in_one_response(): void
    {
        Cache::flush();
        $author = User::factory()->create();

        $event = Event::query()->create([
            'title' => 'Perzeidy',
            'type' => 'meteor_shower',
            'start_at' => now()->addDay(),
            'max_at' => now()->addDay(),
            'visibility' => 1,
            'source_name' => 'manual',
            'source_uid' => 'sidebar-bundle-event',
            'source_hash' => 'sidebar-bundle-event',
        ]);

        BlogPost::query()->create([
            'user_id' => $author->id,
            'title' => 'Sidebar article',
            'slug' => 'sidebar-article',
            'content' => 'x',
            'views' => 12,
            'published_at' => now()->subMinute(),
            'created_at' => now()->subMinute(),
            'updated_at' => now()->subMinute(),
        ]);

        $response = $this->getJson('/api/sidebar-data?sections[]=next_event&sections[]=upcoming_events&sections[]=latest_articles&sections[]=unknown')
            ->assertOk()
            ->assertJsonPath('requested_sections.0', 'next_event')
            ->assertJsonPath('requested_sections.1', 'upcoming_events')
            ->assertJsonPath('requested_sections.2', 'latest_articles')
            ->assertJsonMissingPath('data.unknown');

        $this->assertSame($event->id, $response->json('data.next_event.data.id'));
        $this->assertSame($event->id, $response->json('data.upcoming_events.items.0.id'));
        $this->assertSame('sidebar-article', $response->json('data.latest_articles.latest.0.slug'));
    }

    public function test_it_can_bundle_nasa_payload(): void
    {
        Cache::flush();
        Http::preventStrayRequests();
        $this->mock(TranslationService::class, function ($mock): void {
            $mock->shouldReceive('translateEnToSk')
                ->twice()
                ->andReturnUsing(static fn (string $text) => 'SK ' . $text);
        });

        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8" ?>
<rss version="2.0">
  <channel>
    <item>
      <title>Bundled NASA</title>
      <link>https://www.nasa.gov/image-detail/bundled/</link>
      <description>Bundled description</description>
      <enclosure url="https://www.nasa.gov/wp-content/uploads/bundled.jpg" length="1234" type="image/jpeg" />
    </item>
  </channel>
</rss>
XML;

        Http::fake([
            'https://www.nasa.gov/feeds/iotd-feed/*' => Http::response($xml, 200, ['Content-Type' => 'application/rss+xml']),
        ]);

        $this->getJson('/api/sidebar-data?sections=nasa_apod')
            ->assertOk()
            ->assertJsonPath('requested_sections.0', 'nasa_apod')
            ->assertJsonPath('data.nasa_apod.available', true)
            ->assertJsonPath('data.nasa_apod.title', 'SK Bundled NASA')
            ->assertJsonPath('data.nasa_apod.source.label', 'NASA IOTD RSS');
    }

    public function test_it_can_bundle_neo_watchlist_payload(): void
    {
        Cache::flush();
        config()->set('observing.providers.jpl_sbdd_url', 'https://sbddb.test/query');

        Http::fake([
            'https://sbddb.test/query*' => Http::response([
                'fields' => ['full_name', 'pdes', 'class', 'neo', 'pha', 'moid', 'diameter', 'H'],
                'data' => [
                    ['99942 Apophis', '99942', 'APO', 'Y', 'Y', '0.00026', '0.37', '19.7'],
                    ['2001 FO32', '2001 FO32', 'APO', 'Y', 'N', '0.0035', '0.97', '17.8'],
                ],
            ], 200),
        ]);

        $this->getJson('/api/sidebar-data?sections=neo_watchlist')
            ->assertOk()
            ->assertJsonPath('requested_sections.0', 'neo_watchlist')
            ->assertJsonPath('data.neo_watchlist.available', true)
            ->assertJsonPath('data.neo_watchlist.items.0.name', '99942 Apophis')
            ->assertJsonPath('data.neo_watchlist.source.label', 'NASA JPL SBDB');
    }

    public function test_it_can_bundle_upcoming_launches_payload(): void
    {
        Cache::flush();
        config()->set('observing.providers.launch_library_upcoming_url', 'https://launches.test/upcoming/');

        Http::fake([
            'https://launches.test/upcoming/*' => Http::response([
                'results' => [
                    [
                        'id' => 'launch-1',
                        'name' => 'Falcon 9 Block 5 | Starlink Group 17-24',
                        'status' => [
                            'name' => 'Go for Launch',
                            'abbrev' => 'Go',
                        ],
                        'net' => '2026-03-16T19:00:00Z',
                        'lsp_name' => 'SpaceX',
                        'pad' => 'SLC-40',
                        'location' => 'Cape Canaveral, FL, USA',
                    ],
                ],
            ], 200),
        ]);

        $this->getJson('/api/sidebar-data?sections=upcoming_launches')
            ->assertOk()
            ->assertJsonPath('requested_sections.0', 'upcoming_launches')
            ->assertJsonPath('data.upcoming_launches.available', true)
            ->assertJsonPath('data.upcoming_launches.items.0.name', 'Falcon 9 Block 5 | Starlink Group 17-24')
            ->assertJsonPath('data.upcoming_launches.source.label', 'The Space Devs Launch Library 2');
    }

    public function test_it_bundles_space_weather_and_aurora_with_one_shared_noaa_fetch_cycle(): void
    {
        Cache::flush();
        config()->set('observing.providers.swpc_planetary_k_index_url', 'https://swpc.test/planetary_k_index_1m.json');
        config()->set('observing.providers.swpc_aurora_latest_url', 'https://swpc.test/ovation_aurora_latest.json');

        Http::fake([
            'https://swpc.test/planetary_k_index_1m.json' => Http::response([
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

        $this->getJson('/api/sidebar-data?sections[]=space_weather&sections[]=aurora_watch&lat=48.1486&lon=17.1077&tz=Europe/Bratislava')
            ->assertOk()
            ->assertJsonPath('requested_sections.0', 'space_weather')
            ->assertJsonPath('requested_sections.1', 'aurora_watch')
            ->assertJsonPath('data.space_weather.kp_index', 6)
            ->assertJsonPath('data.aurora_watch.watch_label', 'Vysoka sanca')
            ->assertJsonPath('data.aurora_watch.source.label', 'NOAA SWPC OVATION');

        Http::assertSentCount(2);
    }

    public function test_it_bundles_observing_widgets_with_shared_payload_contracts(): void
    {
        Cache::flush();
        config()->set('observing.providers.jpl_horizons_url', '');
        config()->set('observing.sky_summary.microservice_base', 'http://sky.test');
        config()->set('observing.providers.light_pollution_url', 'https://light.test/provider');
        config()->set('observing.providers.light_pollution_secondary_url', '');
        config()->set('observing.providers.light_pollution_viirs_url', '');
        config()->set('observing.providers.celestrak_gp_url', 'https://celestrak.test/gp.php');
        config()->set('observing.providers.iss_tracker_url', 'https://tracker.test/iss');

        Http::fake([
            'https://api.open-meteo.com/*' => Http::response($this->openMeteoPayload(), 200),
            'https://aa.usno.navy.mil/*' => Http::response($this->usnoPayload(), 200),
            'http://sky.test/sky-summary*' => Http::response([
                'moon' => [
                    'rise_local' => '18:12',
                    'set_local' => '05:48',
                    'altitude_hourly' => [
                        ['local_time' => '19:00', 'altitude_deg' => 12.4],
                        ['local_time' => '20:00', 'altitude_deg' => 18.1],
                    ],
                ],
                'sample_at' => '2026-03-15T20:10:00+01:00',
                'sun_altitude_deg' => -22.4,
                'planets' => [
                    [
                        'name' => 'Jupiter',
                        'alt_max_deg' => 41.3,
                        'az_at_best_deg' => 197.1,
                        'elongation_deg' => 132.1,
                        'direction' => 'S',
                        'best_from' => '19:40',
                        'best_to' => '23:10',
                        'magnitude' => -2.3,
                    ],
                ],
            ], 200),
            'https://light.test/provider*' => Http::response([
                'bortle_class' => 5,
                'brightness_value' => 0.32,
                'confidence' => 'med',
            ], 200),
            'http://sky.test/iss-preview*' => Http::response([
                'available' => true,
                'next_pass_at' => '2026-03-15T21:18:00+01:00',
                'duration_sec' => 420,
                'max_altitude_deg' => 41.3,
                'direction_start' => 'W',
                'direction_end' => 'E',
            ], 200),
            'https://celestrak.test/gp.php*' => Http::response([
                [
                    'OBJECT_NAME' => 'ISS (ZARYA)',
                    'NORAD_CAT_ID' => 25544,
                    'EPOCH' => '2026-03-15T19:40:00Z',
                ],
            ], 200),
            'https://tracker.test/iss' => Http::response([
                'latitude' => 12.34,
                'longitude' => 56.78,
                'timestamp' => 1773600000,
            ], 200),
        ]);

        $response = $this->getJson('/api/sidebar-data?sections[]=observing_conditions&sections[]=observing_weather&sections[]=night_sky&sections[]=iss_pass&lat=48.1486&lon=17.1077&tz=Europe/Bratislava')
            ->assertOk()
            ->assertJsonPath('requested_sections.0', 'observing_conditions')
            ->assertJsonPath('requested_sections.1', 'observing_weather')
            ->assertJsonPath('requested_sections.2', 'night_sky')
            ->assertJsonPath('requested_sections.3', 'iss_pass')
            ->assertJsonPath('data.observing_conditions.weather.cloud_percent', 32)
            ->assertJsonPath('data.observing_conditions.astronomy.moon_phase', 'waning_crescent')
            ->assertJsonPath('data.observing_weather.weather.source', 'open_meteo')
            ->assertJsonPath('data.night_sky.visible_planets.planets.0.name', 'Jupiter')
            ->assertJsonPath('data.night_sky.light_pollution.source', 'light_pollution_provider')
            ->assertJsonPath('data.iss_pass.iss_preview.available', true)
            ->assertJsonPath('data.iss_pass.iss_preview.tracker.source', 'iss_tracker');

        $this->assertSame('2026-03-15T20:10:00+01:00', $response->json('data.night_sky.astronomy.sample_at'));
    }

    private function openMeteoPayload(): array
    {
        return [
            'current' => [
                'time' => '2026-03-15T20:00',
                'relative_humidity_2m' => 56,
                'cloud_cover' => 32,
                'wind_speed_10m' => 13.0,
                'temperature_2m' => 9.8,
                'apparent_temperature' => 8.9,
                'weather_code' => 1,
            ],
            'hourly' => [
                'time' => [
                    '2026-03-15T19:00',
                    '2026-03-15T20:00',
                ],
                'relative_humidity_2m' => [56, 56],
                'cloud_cover' => [32, 32],
                'wind_speed_10m' => [13.0, 13.0],
                'temperature_2m' => [9.8, 9.8],
            ],
        ];
    }

    private function usnoPayload(): array
    {
        return [
            'properties' => [
                'data' => [
                    'curphase' => 'Waning Crescent',
                    'fracillum' => '14%',
                    'sundata' => [
                        ['phen' => 'Rise', 'time' => '06:09'],
                        ['phen' => 'Set', 'time' => '18:06'],
                        ['phen' => 'End Civil Twilight', 'time' => '18:40'],
                    ],
                ],
            ],
        ];
    }
}
