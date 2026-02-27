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
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T.*[+-]\d{2}:\d{2}$/', (string) $response->json('as_of'));
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
        Http::assertSentCount(1);
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

    private function openMeteoPayload(int $cloud, int $humidity, float $wind): array
    {
        return [
            'current' => [
                'relative_humidity_2m' => $humidity,
                'cloud_cover' => $cloud,
                'wind_speed_10m' => $wind,
                'temperature_2m' => 2.8,
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
            ],
        ];
    }
}
