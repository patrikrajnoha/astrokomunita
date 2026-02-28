<?php

namespace Tests\Feature\Sky;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SkyLightPollutionEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_provider_payload_when_provider_is_configured(): void
    {
        Cache::flush();
        Config::set('observing.providers.light_pollution_url', 'https://provider.example/light');

        Http::fake([
            'https://provider.example/light*' => Http::response([
                'bortle_class' => 6,
                'brightness_value' => 0.123,
                'confidence' => 'high',
            ], 200),
        ]);

        $this->getJson('/api/sky/light-pollution?lat=48.1486&lon=17.1077')
            ->assertOk()
            ->assertJson([
                'bortle_class' => 6,
                'brightness_value' => 0.123,
                'confidence' => 'high',
            ]);
    }

    public function test_it_returns_fallback_payload_when_provider_is_missing_or_fails(): void
    {
        Cache::flush();
        Config::set('observing.providers.light_pollution_url', 'https://provider.example/light');

        Http::fake([
            'https://provider.example/light*' => Http::response(['message' => 'error'], 503),
        ]);

        $response = $this->getJson('/api/sky/light-pollution?lat=48.1486&lon=17.1077');

        $response->assertOk()
            ->assertJsonStructure([
                'bortle_class',
                'brightness_value',
                'confidence',
            ]);

        $this->assertGreaterThanOrEqual(1, (int) $response->json('bortle_class'));
        $this->assertLessThanOrEqual(9, (int) $response->json('bortle_class'));
        $this->assertContains($response->json('confidence'), ['low', 'med', 'high']);
    }
}
