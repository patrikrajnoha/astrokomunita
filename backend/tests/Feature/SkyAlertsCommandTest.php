<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\NotificationEvent;
use App\Models\User;
use App\Models\UserNotificationPreference;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SkyAlertsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_sends_iss_and_good_conditions_alerts_with_dedupe(): void
    {
        CarbonImmutable::setTestNow('2026-03-01 17:05:00Z');

        $user = User::factory()->create([
            'latitude' => 48.1486,
            'longitude' => 17.1077,
            'timezone' => 'Europe/Bratislava',
            'location_label' => 'Bratislava',
            'location_source' => 'manual',
        ]);

        UserNotificationPreference::create([
            'user_id' => $user->id,
            'iss_alerts' => true,
            'good_conditions_alerts' => true,
        ]);

        $issRise = CarbonImmutable::now('UTC')->addMinutes(8)->timestamp;

        Http::fake([
            'http://api.open-notify.org/iss-pass.json*' => Http::response([
                'message' => 'success',
                'response' => [
                    [
                        'duration' => 420,
                        'risetime' => $issRise,
                    ],
                ],
            ], 200),
            'https://api.open-meteo.com/*' => Http::response($this->openMeteoGoodWeatherPayload(), 200),
        ]);

        Artisan::call('notifications:send-sky-alerts');
        Artisan::call('notifications:send-sky-alerts');

        $this->assertSame(1, Notification::query()->where('type', 'iss_pass_alert')->count());
        $this->assertSame(1, Notification::query()->where('type', 'good_conditions_alert')->count());
        $this->assertSame(2, NotificationEvent::query()->count());

        $goodConditions = Notification::query()->where('type', 'good_conditions_alert')->first();
        $this->assertNotNull($goodConditions);
        $this->assertSame('2026-03-01', $goodConditions->data['local_date'] ?? null);

        CarbonImmutable::setTestNow();
    }

    private function openMeteoGoodWeatherPayload(): array
    {
        return [
            'current' => [
                'relative_humidity_2m' => 20,
                'cloud_cover' => 10,
                'wind_speed_10m' => 2.0,
                'temperature_2m' => 5.1,
                'apparent_temperature' => 4.2,
                'weather_code' => 1,
            ],
            'hourly' => [
                'time' => [
                    '2026-03-01T18:00',
                    '2026-03-01T19:00',
                ],
                'relative_humidity_2m' => [20, 22],
                'cloud_cover' => [10, 12],
                'wind_speed_10m' => [2.0, 2.5],
            ],
        ];
    }
}
