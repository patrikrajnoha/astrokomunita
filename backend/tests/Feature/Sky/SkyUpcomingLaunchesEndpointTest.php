<?php

namespace Tests\Feature\Sky;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SkyUpcomingLaunchesEndpointTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Cache::flush();
        parent::tearDown();
    }

    public function test_it_returns_a_normalized_upcoming_launches_payload(): void
    {
        config()->set('observing.providers.launch_library_upcoming_url', 'https://launches.test/upcoming/');

        Http::fake([
            'https://launches.test/upcoming/*' => Http::response([
                'results' => [
                    [
                        'id' => 'launch-1',
                        'name' => 'Falcon 9 Block 5 | Starlink Group 17-24',
                        'slug' => 'falcon-9-block-5-starlink-group-17-24',
                        'status' => [
                            'name' => 'Go for Launch',
                            'abbrev' => 'Go',
                            'description' => 'Confirmed launch time.',
                        ],
                        'last_updated' => '2026-03-15T08:30:00Z',
                        'net' => '2026-03-16T19:00:00Z',
                        'window_start' => '2026-03-16T18:45:00Z',
                        'window_end' => '2026-03-16T19:15:00Z',
                        'lsp_name' => 'SpaceX',
                        'mission' => 'Starlink Group 17-24',
                        'mission_type' => 'Communications',
                        'pad' => 'SLC-40',
                        'location' => 'Cape Canaveral, FL, USA',
                    ],
                ],
            ], 200),
        ]);

        $this->getJson('/api/sky/upcoming-launches')
            ->assertOk()
            ->assertJsonPath('available', true)
            ->assertJsonPath('source.provider', 'launch_library_2')
            ->assertJsonPath('source.label', 'The Space Devs Launch Library 2')
            ->assertJsonPath('items.0.name', 'Falcon 9 Block 5 | Starlink Group 17-24')
            ->assertJsonPath('items.0.provider', 'SpaceX')
            ->assertJsonPath('items.0.status.abbrev', 'Go')
            ->assertJsonPath('items.0.mission_name', 'Starlink Group 17-24')
            ->assertJsonPath('items.0.pad', 'SLC-40');
    }

    public function test_it_caches_upcoming_launches_payloads(): void
    {
        config()->set('observing.providers.launch_library_upcoming_url', 'https://launches.test/upcoming/');

        Http::fake([
            'https://launches.test/upcoming/*' => Http::sequence()
                ->push([
                    'results' => [
                        [
                            'id' => 'launch-1',
                            'name' => 'Launch One',
                            'status' => ['name' => 'Go for Launch', 'abbrev' => 'Go'],
                            'net' => '2026-03-16T19:00:00Z',
                        ],
                    ],
                ], 200)
                ->push([
                    'results' => [
                        [
                            'id' => 'launch-2',
                            'name' => 'Launch Two',
                            'status' => ['name' => 'To Be Determined', 'abbrev' => 'TBD'],
                            'net' => '2026-03-17T19:00:00Z',
                        ],
                    ],
                ], 200),
        ]);

        $first = $this->getJson('/api/sky/upcoming-launches')->assertOk();
        $second = $this->getJson('/api/sky/upcoming-launches')->assertOk();

        $this->assertSame($first->json('items.0.name'), $second->json('items.0.name'));
        Http::assertSentCount(1);
    }

    public function test_it_falls_back_to_last_known_launches_when_provider_is_temporarily_unavailable(): void
    {
        config()->set('observing.providers.launch_library_upcoming_url', 'https://launches.test/upcoming/');

        Http::fake([
            'https://launches.test/upcoming/*' => Http::sequence()
                ->push([
                    'results' => [
                        [
                            'id' => 'launch-1',
                            'name' => 'Launch One',
                            'status' => ['name' => 'Go for Launch', 'abbrev' => 'Go'],
                            'net' => '2026-03-16T19:00:00Z',
                        ],
                    ],
                ], 200)
                ->pushStatus(503),
        ]);

        $this->getJson('/api/sky/upcoming-launches')
            ->assertOk()
            ->assertJsonPath('available', true)
            ->assertJsonPath('items.0.name', 'Launch One');

        Cache::forget('sky_upcoming_launches:v1:3');

        $this->getJson('/api/sky/upcoming-launches')
            ->assertOk()
            ->assertJsonPath('available', true)
            ->assertJsonPath('items.0.name', 'Launch One')
            ->assertJsonPath('stale', true);

        Http::assertSentCount(2);
    }

    public function test_it_marks_payload_unavailable_when_provider_returns_no_items(): void
    {
        config()->set('observing.providers.launch_library_upcoming_url', 'https://launches.test/upcoming/');

        Http::fake([
            'https://launches.test/upcoming/*' => Http::response(['results' => []], 200),
        ]);

        $this->getJson('/api/sky/upcoming-launches')
            ->assertOk()
            ->assertJsonPath('available', false)
            ->assertJsonPath('reason', 'provider_unavailable');
    }
}
