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

        Http::fake([
            'http://api.open-notify.org/iss-pass.json*' => Http::response([
                'message' => 'success',
                'response' => [
                    [
                        'duration' => 420,
                        'risetime' => 1772408400,
                    ],
                ],
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

        Http::fake([
            'http://api.open-notify.org/iss-pass.json*' => Http::sequence()
                ->push([
                    'message' => 'success',
                    'response' => [
                        ['duration' => 400, 'risetime' => 1772408400],
                    ],
                ], 200)
                ->push([
                    'message' => 'success',
                    'response' => [
                        ['duration' => 120, 'risetime' => 1772409000],
                    ],
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

        Http::fake([
            'http://api.open-notify.org/iss-pass.json*' => Http::response(['message' => 'failure'], 503),
        ]);

        $this->getJson('/api/sky/iss-preview?lat=48.1486&lon=17.1077&tz=Europe/Bratislava')
            ->assertOk()
            ->assertJsonPath('available', false);
    }
}
