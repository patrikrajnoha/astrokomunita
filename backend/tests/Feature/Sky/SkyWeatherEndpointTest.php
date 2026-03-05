<?php

namespace Tests\Feature\Sky;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SkyWeatherEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_weather_payload_shape(): void
    {
        Cache::flush();

        Http::fake([
            'https://api.open-meteo.com/*' => Http::response($this->openMeteoPayload(
                cloud: 32,
                humidity: 58,
                wind: 11.4
            ), 200),
        ]);

        $response = $this->getJson('/api/sky/weather?lat=48.1486&lon=17.1077&tz=Europe/Bratislava');

        $response->assertOk()
            ->assertJsonStructure([
                'cloud_percent',
                'wind_speed',
                'wind_unit',
                'humidity_percent',
                'observing_score',
                'updated_at',
                'as_of',
                'source',
            ])
            ->assertJsonPath('source', 'open_meteo')
            ->assertJsonPath('wind_unit', 'km/h');

        $this->assertIsInt($response->json('cloud_percent'));
        $this->assertIsInt($response->json('humidity_percent'));
        $this->assertIsInt($response->json('observing_score'));
        $this->assertGreaterThanOrEqual(0, $response->json('observing_score'));
        $this->assertLessThanOrEqual(100, $response->json('observing_score'));
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T.*[+-]\d{2}:\d{2}$/', (string) $response->json('updated_at'));
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T.*[+-]\d{2}:\d{2}$/', (string) $response->json('as_of'));
        $this->assertSame($response->json('updated_at'), $response->json('as_of'));
    }

    public function test_it_caches_weather_payload(): void
    {
        Cache::flush();

        Http::fake([
            'https://api.open-meteo.com/*' => Http::sequence()
                ->push($this->openMeteoPayload(cloud: 21, humidity: 49, wind: 7.2), 200)
                ->push($this->openMeteoPayload(cloud: 88, humidity: 91, wind: 35.0), 200),
        ]);

        $first = $this->getJson('/api/sky/weather?lat=48.1486&lon=17.1077&tz=Europe/Bratislava')
            ->assertOk();
        $second = $this->getJson('/api/sky/weather?lat=48.1486&lon=17.1077&tz=Europe/Bratislava')
            ->assertOk();

        $this->assertSame($first->json('cloud_percent'), $second->json('cloud_percent'));
        $this->assertSame($first->json('humidity_percent'), $second->json('humidity_percent'));
        $this->assertSame($first->json('observing_score'), $second->json('observing_score'));
        $this->assertSame($first->json('updated_at'), $second->json('updated_at'));
        Http::assertSentCount(1);
    }

    public function test_it_prefers_current_temperature_over_hourly_values_for_current_conditions(): void
    {
        Cache::flush();

        Http::fake([
            'https://api.open-meteo.com/*' => Http::response($this->openMeteoPayload(
                cloud: 3,
                humidity: 42,
                wind: 12.4,
                currentTemperature: 11.1,
                hourlyTemperatures: [28.4, 27.9]
            ), 200),
        ]);

        $response = $this->getJson('/api/sky/weather?lat=48.3064&lon=18.0764&tz=Europe/Bratislava')
            ->assertOk();

        $this->assertSame(11.1, $response->json('temperature_c'));
    }

    public function test_weather_cache_is_isolated_by_coordinates(): void
    {
        Cache::flush();

        Http::fake([
            'https://api.open-meteo.com/*' => Http::sequence()
                ->push($this->openMeteoPayload(cloud: 5, humidity: 40, wind: 8.0, currentTemperature: 11.0), 200)
                ->push($this->openMeteoPayload(cloud: 90, humidity: 90, wind: 30.0, currentTemperature: 22.0), 200),
        ]);

        $first = $this->getJson('/api/sky/weather?lat=48.3064&lon=18.0764&tz=Europe/Bratislava')->assertOk();
        $second = $this->getJson('/api/sky/weather?lat=48.3064&lon=18.1764&tz=Europe/Bratislava')->assertOk();

        $this->assertNotSame($first->json('temperature_c'), $second->json('temperature_c'));
        Http::assertSentCount(2);
    }

    public function test_weather_cache_key_contains_coordinates_and_provider_suffix(): void
    {
        Cache::shouldReceive('remember')
            ->once()
            ->withArgs(function ($key, $ttl, $resolver): bool {
                $this->assertStringContainsString('sky_weather', $key);
                $this->assertStringContainsString('48.306400', $key);
                $this->assertStringContainsString('18.076400', $key);
                $this->assertStringContainsString('Europe/Bratislava', $key);
                $this->assertStringEndsWith(':open_meteo', $key);
                $this->assertIsCallable($resolver);

                return true;
            })
            ->andReturn([
                'cloud_percent' => 0,
                'wind_speed' => 12.4,
                'wind_unit' => 'km/h',
                'humidity_percent' => 42,
                'temperature_c' => 11.1,
                'apparent_temperature_c' => 7.0,
                'weather_code' => 0,
                'weather_label' => 'Jasno',
                'observing_score' => 82,
                'updated_at' => '2026-03-05T10:30:00+01:00',
                'as_of' => '2026-03-05T10:30:00+01:00',
                'source' => 'open_meteo',
            ]);

        $this->getJson('/api/sky/weather?lat=48.3064&lon=18.0764&tz=Europe/Bratislava')
            ->assertOk()
            ->assertJsonPath('source', 'open_meteo');
    }

    public function test_it_uses_user_canonical_location_when_query_coordinates_are_missing(): void
    {
        Cache::flush();

        $user = User::factory()->create([
            'latitude' => 49.1234567,
            'longitude' => 18.7654321,
            'timezone' => 'Europe/Prague',
            'location_label' => 'Custom place',
            'location_source' => 'manual',
        ]);

        Http::fake([
            'https://api.open-meteo.com/*' => Http::response($this->openMeteoPayload(
                cloud: 44,
                humidity: 70,
                wind: 14.2
            ), 200),
        ]);

        $response = $this->actingAs($user)->getJson('/api/sky/weather?tz=Invalid/Timezone');
        $response->assertOk();

        Http::assertSent(function ($request) {
            parse_str(parse_url($request->url(), PHP_URL_QUERY) ?: '', $query);

            return ($query['latitude'] ?? null) === '49.123457'
                && ($query['longitude'] ?? null) === '18.765432'
                && ($query['timezone'] ?? null) === 'Europe/Prague';
        });
    }

    private function openMeteoPayload(
        int $cloud,
        int $humidity,
        float $wind,
        float $currentTemperature = 2.8,
        array $hourlyTemperatures = [2.8, 2.8]
    ): array
    {
        return [
            'current' => [
                'time' => '2026-02-27T19:30',
                'relative_humidity_2m' => $humidity,
                'cloud_cover' => $cloud,
                'wind_speed_10m' => $wind,
                'temperature_2m' => $currentTemperature,
                'apparent_temperature' => 1.3,
                'weather_code' => 2,
            ],
            'hourly' => [
                'time' => [
                    '2026-02-27T18:00',
                    '2026-02-27T19:00',
                ],
                'relative_humidity_2m' => [$humidity, $humidity],
                'cloud_cover' => [$cloud, $cloud],
                'wind_speed_10m' => [$wind, $wind],
                'temperature_2m' => $hourlyTemperatures,
            ],
        ];
    }
}
