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
        Config::set('observing.providers.light_pollution_secondary_url', '');
        Config::set('observing.providers.light_pollution_viirs_url', '');

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
                'source' => 'light_pollution_provider',
                'reason' => null,
            ])
            ->assertJsonPath('measurement.kind', 'provider_normalized')
            ->assertJsonPath('measurement.normalization', 'provider_value_to_bortle_v1')
            ->assertJsonPath('provenance.method', 'provider_http_get')
            ->assertJsonPath('provenance.provider_key', 'light_pollution_provider');
    }

    public function test_it_uses_secondary_provider_when_primary_provider_is_unavailable(): void
    {
        Cache::flush();
        Config::set('observing.providers.light_pollution_url', 'https://provider.example/light');
        Config::set('observing.providers.light_pollution_secondary_url', 'https://provider-secondary.example/light');
        Config::set('observing.providers.light_pollution_viirs_url', '');

        Http::fake([
            'https://provider.example/light*' => Http::response(['message' => 'error'], 503),
            'https://provider-secondary.example/light*' => Http::response([
                'bortle_class' => 4,
                'brightness_value' => 0.15,
                'confidence' => 'med',
            ], 200),
        ]);

        $this->getJson('/api/sky/light-pollution?lat=48.1486&lon=17.1077')
            ->assertOk()
            ->assertJsonPath('bortle_class', 4)
            ->assertJsonPath('brightness_value', 0.15)
            ->assertJsonPath('confidence', 'med')
            ->assertJsonPath('source', 'light_pollution_provider_secondary')
            ->assertJsonPath('reason', null)
            ->assertJsonPath('measurement.kind', 'provider_normalized')
            ->assertJsonPath('provenance.provider_key', 'light_pollution_provider_secondary');
    }

    public function test_it_uses_viirs_fallback_when_other_providers_are_unavailable(): void
    {
        Cache::flush();
        Config::set('observing.providers.light_pollution_url', 'https://provider.example/light');
        Config::set('observing.providers.light_pollution_secondary_url', 'https://provider-secondary.example/light');
        Config::set('observing.providers.light_pollution_viirs_url', 'https://viirs.example/getSamples');

        Http::fake([
            'https://provider.example/light*' => Http::response(['message' => 'error'], 503),
            'https://provider-secondary.example/light*' => Http::response(['message' => 'error'], 503),
            'https://viirs.example/getSamples*' => Http::response([
                'samples' => [[
                    'value' => '39.94',
                    'resolution' => 0.004166,
                ]],
            ], 200),
        ]);

        $this->getJson('/api/sky/light-pollution?lat=48.1486&lon=17.1077')
            ->assertOk()
            ->assertJsonPath('bortle_class', 8)
            ->assertJsonPath('brightness_value', 0.875)
            ->assertJsonPath('confidence', 'med')
            ->assertJsonPath('source', 'light_pollution_viirs')
            ->assertJsonPath('reason', null)
            ->assertJsonPath('measurement.kind', 'viirs_radiance')
            ->assertJsonPath('measurement.viirs_radiance_nw_cm2_sr', 39.94)
            ->assertJsonPath('measurement.bortle_mapping_version', 'viirs_radiance_to_bortle_v1')
            ->assertJsonPath('provenance.method', 'viirs_get_samples_nearest_neighbor')
            ->assertJsonPath('provenance.provider_key', 'light_pollution_viirs');
    }

    public function test_it_returns_unavailable_payload_when_provider_fails(): void
    {
        Cache::flush();
        Config::set('observing.providers.light_pollution_url', 'https://provider.example/light');
        Config::set('observing.providers.light_pollution_secondary_url', '');
        Config::set('observing.providers.light_pollution_viirs_url', '');

        Http::fake([
            'https://provider.example/light*' => Http::response(['message' => 'error'], 503),
        ]);

        $response = $this->getJson('/api/sky/light-pollution?lat=48.1486&lon=17.1077');

        $response->assertOk()
            ->assertJsonPath('bortle_class', null)
            ->assertJsonPath('brightness_value', null)
            ->assertJsonPath('confidence', 'low')
            ->assertJsonPath('source', 'light_pollution_provider')
            ->assertJsonPath('reason', 'light_pollution_provider_unavailable')
            ->assertJsonPath('measurement', null)
            ->assertJsonPath('provenance.method', 'unavailable');
    }

    public function test_it_returns_not_configured_when_provider_url_missing(): void
    {
        Cache::flush();
        Config::set('observing.providers.light_pollution_url', '');
        Config::set('observing.providers.light_pollution_secondary_url', '');
        Config::set('observing.providers.light_pollution_viirs_url', '');

        $response = $this->getJson('/api/sky/light-pollution?lat=48.1486&lon=17.1077');

        $response->assertOk()
            ->assertJsonPath('bortle_class', null)
            ->assertJsonPath('brightness_value', null)
            ->assertJsonPath('confidence', 'low')
            ->assertJsonPath('reason', 'light_pollution_provider_not_configured')
            ->assertJsonPath('measurement', null)
            ->assertJsonPath('provenance.method', 'unavailable');
    }

    public function test_it_returns_last_known_payload_when_provider_is_temporarily_unavailable(): void
    {
        Cache::flush();
        Config::set('observing.providers.light_pollution_url', 'https://provider.example/light');
        Config::set('observing.providers.light_pollution_secondary_url', '');
        Config::set('observing.providers.light_pollution_viirs_url', '');
        Config::set('observing.sky.light_pollution_cache_ttl_hours', 24);
        Config::set('observing.sky.light_pollution_last_known_ttl_hours', 168);

        Http::fake([
            'https://provider.example/light*' => Http::sequence()
                ->push([
                    'bortle_class' => 5,
                    'brightness_value' => 0.18,
                    'confidence' => 'high',
                ], 200)
                ->push(['message' => 'offline'], 503),
        ]);

        $this->getJson('/api/sky/light-pollution?lat=48.1486&lon=17.1077&tz=Europe/Bratislava')
            ->assertOk()
            ->assertJsonPath('source', 'light_pollution_provider')
            ->assertJsonPath('reason', null)
            ->assertJsonPath('bortle_class', 5)
            ->assertJsonPath('measurement.kind', 'provider_normalized')
            ->assertJsonPath('provenance.method', 'provider_http_get');

        Cache::forget('sky_light_pollution:48.148600:17.107700:Europe/Bratislava');

        $this->getJson('/api/sky/light-pollution?lat=48.1486&lon=17.1077&tz=Europe/Bratislava')
            ->assertOk()
            ->assertJsonPath('source', 'light_pollution_cached')
            ->assertJsonPath('reason', 'using_cached_data')
            ->assertJsonPath('confidence', 'med')
            ->assertJsonPath('bortle_class', 5)
            ->assertJsonPath('brightness_value', 0.18)
            ->assertJsonPath('measurement.kind', 'provider_normalized')
            ->assertJsonPath('provenance.cache_mode', 'last_known');
    }
}
