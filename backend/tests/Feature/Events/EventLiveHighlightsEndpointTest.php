<?php

namespace Tests\Feature\Events;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EventLiveHighlightsEndpointTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Cache::flush();
        parent::tearDown();
    }

    public function test_it_returns_location_required_without_explicit_or_user_coordinates(): void
    {
        Cache::flush();
        Http::preventStrayRequests();

        $this->getJson('/api/events/live-highlights')
            ->assertOk()
            ->assertJsonPath('meta.location_required', true)
            ->assertJsonPath('meta.reason', 'location_required')
            ->assertJsonCount(0, 'data');

        Http::assertNothingSent();
    }

    public function test_it_returns_live_aurora_highlight_for_explicit_location(): void
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

        $this->getJson('/api/events/live-highlights?lat=48.1486&lon=17.1077&tz=Europe/Bratislava')
            ->assertOk()
            ->assertJsonPath('meta.location_required', false)
            ->assertJsonPath('meta.context_source', 'query')
            ->assertJsonPath('data.0.key', 'aurora_watch')
            ->assertJsonPath('data.0.type', 'aurora')
            ->assertJsonPath('data.0.title', 'Aurora watch')
            ->assertJsonPath('data.0.badge', 'Zive teraz')
            ->assertJsonPath('data.0.status_label', 'Vysoka sanca')
            ->assertJsonPath('data.0.status_score', 72)
            ->assertJsonPath('data.0.tone', 'high')
            ->assertJsonPath('data.0.source.label', 'NOAA SWPC OVATION');
    }
}
