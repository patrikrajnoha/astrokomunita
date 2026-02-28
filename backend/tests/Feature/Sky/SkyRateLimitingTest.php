<?php

namespace Tests\Feature\Sky;

use App\Models\User;
use App\Services\Sky\SkyVisiblePlanetsService;
use App\Services\Sky\SkyWeatherService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

class SkyRateLimitingTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Cache::flush();

        parent::tearDown();
    }

    public function test_auth_user_exceeding_visible_planets_limit_receives_429(): void
    {
        Cache::flush();

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->app->instance(SkyVisiblePlanetsService::class, Mockery::mock(SkyVisiblePlanetsService::class, function (Mockery\MockInterface $mock): void {
            $mock->shouldReceive('fetch')->andReturn([
                'planets' => [
                    [
                        'name' => 'Jupiter',
                        'altitude_deg' => 52.4,
                        'azimuth_deg' => 140.2,
                        'direction' => 'SE',
                        'quality' => 'excellent',
                        'best_time_window' => '20:00-23:00',
                    ],
                ],
            ]);
        }));

        RateLimiter::clear('sky-expensive-auth|' . $user->id);

        for ($attempt = 0; $attempt < 20; $attempt++) {
            $this->getJson('/api/sky/visible-planets?lat=48.1486&lon=17.1077&tz=Europe/Bratislava')
                ->assertOk();
        }

        $this->getJson('/api/sky/visible-planets?lat=48.1486&lon=17.1077&tz=Europe/Bratislava')
            ->assertStatus(429)
            ->assertJsonPath('message', 'Príliš veľa požiadaviek. Skús znova o chvíľu.');
    }

    public function test_guest_exceeding_weather_limit_receives_429(): void
    {
        Cache::flush();

        $ip = '10.10.10.25';

        $this->app->instance(SkyWeatherService::class, Mockery::mock(SkyWeatherService::class, function (Mockery\MockInterface $mock): void {
            $mock->shouldReceive('fetch')->andReturn([
                'cloud_percent' => 12,
                'wind_speed' => 5,
                'wind_unit' => 'km/h',
                'humidity_percent' => 44,
                'observing_score' => 88,
                'as_of' => now()->toIso8601String(),
                'source' => 'fake_weather',
            ]);
        }));

        RateLimiter::clear('sky-cheap-guest|' . $ip);

        for ($attempt = 0; $attempt < 30; $attempt++) {
            $this->withServerVariables(['REMOTE_ADDR' => $ip])
                ->getJson('/api/sky/weather?lat=48.1486&lon=17.1077&tz=Europe/Bratislava')
                ->assertOk();
        }

        $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->getJson('/api/sky/weather?lat=48.1486&lon=17.1077&tz=Europe/Bratislava')
            ->assertStatus(429)
            ->assertJsonPath('message', 'Príliš veľa požiadaviek. Skús znova o chvíľu.');
    }

    public function test_rate_limited_sky_response_is_http_429_without_bootstrap_exception_override(): void
    {
        Cache::flush();

        $ip = '10.10.10.26';

        $this->app->instance(SkyWeatherService::class, Mockery::mock(SkyWeatherService::class, function (Mockery\MockInterface $mock): void {
            $mock->shouldReceive('fetch')->andReturn([
                'cloud_percent' => 15,
                'wind_speed' => 4,
                'wind_unit' => 'km/h',
                'humidity_percent' => 40,
                'observing_score' => 90,
                'as_of' => now()->toIso8601String(),
                'source' => 'fake_weather',
            ]);
        }));

        RateLimiter::clear('sky-cheap-guest|' . $ip);

        for ($attempt = 0; $attempt < 30; $attempt++) {
            $this->withServerVariables(['REMOTE_ADDR' => $ip])
                ->getJson('/api/sky/weather?lat=48.1486&lon=17.1077&tz=Europe/Bratislava')
                ->assertOk();
        }

        $response = $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->getJson('/api/sky/weather?lat=48.1486&lon=17.1077&tz=Europe/Bratislava');

        $response->assertStatus(429)
            ->assertJsonPath('message', 'Príliš veľa požiadaviek. Skús znova o chvíľu.');

        $this->assertNotSame(500, $response->getStatusCode());
    }
}
