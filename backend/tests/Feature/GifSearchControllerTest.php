<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GifSearchControllerTest extends TestCase
{
    public function test_returns_cached_payload_without_provider_call_on_cache_hit(): void
    {
        config([
            'services.giphy.api_key' => 'test-key',
        ]);

        Http::fake();

        $cacheKey = 'integrations:gifs:search:' . sha1('moon|20|0');
        Cache::put($cacheKey, [
            'data' => [[
                'id' => 'cached-1',
                'title' => 'Cached',
                'preview_url' => 'https://media.giphy.com/media/cached/giphy-downsized.gif',
                'original_url' => 'https://media.giphy.com/media/cached/giphy.gif',
                'width' => 480,
                'height' => 270,
            ]],
            'meta' => ['total_count' => 1, 'count' => 1, 'offset' => 0],
        ], now()->addMinutes(10));

        $this->getJson('/api/integrations/gifs/search?q=Moon')
            ->assertOk()
            ->assertJsonPath('data.0.id', 'cached-1');

        Http::assertNothingSent();
    }

    public function test_returns_429_when_global_quota_guard_is_exhausted(): void
    {
        config([
            'services.giphy.api_key' => 'test-key',
            'services.giphy.global_hourly_limit' => 80,
        ]);

        $hourKey = 'integrations:gifs:global-hourly:' . now()->format('YmdH');
        Cache::put($hourKey, 80, now()->addHour());
        Http::fake();

        $this->getJson('/api/integrations/gifs/search?q=planet')
            ->assertStatus(429)
            ->assertJsonPath('message', 'GIF search docasne nedostupny');
    }

    public function test_maps_giphy_payload_to_frontend_contract(): void
    {
        config([
            'services.giphy.api_key' => 'test-key',
            'services.giphy.global_hourly_limit' => 80,
        ]);

        Http::fake([
            'https://api.giphy.com/v1/gifs/search*' => Http::response([
                'data' => [[
                    'id' => 'abc123',
                    'title' => 'Moon party',
                    'images' => [
                        'fixed_width_small' => [
                            'url' => 'https://media.giphy.com/media/abc123/200w.gif',
                        ],
                        'original' => [
                            'url' => 'https://media.giphy.com/media/abc123/giphy.gif',
                            'width' => '640',
                            'height' => '360',
                        ],
                    ],
                ]],
                'pagination' => [
                    'total_count' => 1000,
                    'count' => 1,
                    'offset' => 0,
                ],
            ], 200),
        ]);

        $response = $this->getJson('/api/integrations/gifs/search?q=moon&limit=1&offset=0');

        $response->assertOk()
            ->assertJsonPath('data.0.id', 'abc123')
            ->assertJsonPath('data.0.title', 'Moon party')
            ->assertJsonPath('data.0.preview_url', 'https://media.giphy.com/media/abc123/200w.gif')
            ->assertJsonPath('data.0.original_url', 'https://media.giphy.com/media/abc123/giphy.gif')
            ->assertJsonPath('data.0.width', 640)
            ->assertJsonPath('data.0.height', 360)
            ->assertJsonPath('meta.total_count', 1000)
            ->assertJsonPath('meta.count', 1)
            ->assertJsonPath('meta.offset', 0);
    }
}
