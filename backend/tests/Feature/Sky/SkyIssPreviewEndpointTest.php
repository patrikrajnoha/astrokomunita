<?php

namespace Tests\Feature\Sky;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SkyIssPreviewEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_iss_preview_payload_shape(): void
    {
        Cache::flush();
        config()->set('observing.sky_summary.microservice_base', 'http://sky.test');

        Http::fake([
            'http://sky.test/iss-preview*' => Http::response([
                'available' => true,
                'next_pass_at' => '2026-02-28T19:20:00+01:00',
                'duration_sec' => 420,
                'max_altitude_deg' => 41.3,
                'direction_start' => 'W',
                'direction_end' => 'E',
            ], 200),
        ]);

        $response = $this->getJson('/api/sky/iss-preview?lat=48.1486&lon=17.1077&tz=Europe/Bratislava');

        $response->assertOk()
            ->assertJsonPath('available', true)
            ->assertJsonStructure([
                'available',
                'next_pass_at',
                'duration_sec',
                'max_altitude_deg',
                'direction_start',
                'direction_end',
            ])
            ->assertJsonPath('duration_sec', 420);

        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T.*[+-]\d{2}:\d{2}$/', (string) $response->json('next_pass_at'));
    }

    public function test_it_caches_iss_preview_payload(): void
    {
        Cache::flush();
        config()->set('observing.sky_summary.microservice_base', 'http://sky.test');

        Http::fake([
            'http://sky.test/iss-preview*' => Http::sequence()
                ->push([
                    'available' => true,
                    'next_pass_at' => '2026-02-28T19:20:00+01:00',
                    'duration_sec' => 400,
                    'max_altitude_deg' => 39.5,
                    'direction_start' => 'W',
                    'direction_end' => 'E',
                ], 200)
                ->push([
                    'available' => true,
                    'next_pass_at' => '2026-02-28T19:30:00+01:00',
                    'duration_sec' => 120,
                    'max_altitude_deg' => 20.1,
                    'direction_start' => 'NW',
                    'direction_end' => 'SE',
                ], 200),
        ]);

        $first = $this->getJson('/api/sky/iss-preview?lat=48.1486&lon=17.1077&tz=Europe/Bratislava')->assertOk();
        $second = $this->getJson('/api/sky/iss-preview?lat=48.1486&lon=17.1077&tz=Europe/Bratislava')->assertOk();

        $this->assertSame($first->json('next_pass_at'), $second->json('next_pass_at'));
        Http::assertSentCount(1);
    }

    public function test_it_returns_available_false_when_provider_fails(): void
    {
        Cache::flush();
        config()->set('observing.sky_summary.microservice_base', 'http://sky.test');

        Http::fake([
            'http://sky.test/iss-preview*' => Http::response(['message' => 'failure'], 503),
        ]);

        $this->getJson('/api/sky/iss-preview?lat=48.1486&lon=17.1077&tz=Europe/Bratislava')
            ->assertOk()
            ->assertJsonPath('available', false);
    }
}
