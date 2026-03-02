<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EventViewingForecastTest extends TestCase
{
    use RefreshDatabase;

    public function test_missing_location_returns_400(): void
    {
        Cache::flush();

        $event = $this->createPublishedEvent();

        $response = $this->getJson("/api/events/{$event->id}/viewing-forecast");

        $response
            ->assertStatus(400)
            ->assertJsonPath('success', false)
            ->assertJsonPath('errors.code', 'missing_location');
    }

    public function test_it_returns_viewing_window_and_aggregated_forecast_summary(): void
    {
        Cache::flush();

        $user = User::factory()->create([
            'latitude' => 48.1486,
            'longitude' => 17.1077,
            'timezone' => 'Europe/Bratislava',
            'location_label' => 'Bratislava',
            'location_source' => 'manual',
        ]);
        $event = $this->createPublishedEvent([
            'start_at' => CarbonImmutable::parse('2026-03-14 18:00:00', 'UTC'),
            'end_at' => CarbonImmutable::parse('2026-03-14 22:30:00', 'UTC'),
            'max_at' => CarbonImmutable::parse('2026-03-14 20:30:00', 'UTC'),
        ]);

        Http::fake(function ($request) {
            $url = $request->url();

            if (str_starts_with($url, 'https://aa.usno.navy.mil/')) {
                parse_str(parse_url($url, PHP_URL_QUERY) ?: '', $query);
                $date = $query['date'] ?? null;

                return match ($date) {
                    '2026-03-13' => Http::response($this->usnoPayload('06:13', '18:03', '19:59', '20:09'), 200),
                    '2026-03-14' => Http::response($this->usnoPayload('06:11', '18:05', '05:39', '20:10'), 200),
                    '2026-03-15' => Http::response($this->usnoPayload('06:09', '18:06', '05:37', '20:12'), 200),
                    default => Http::response([], 404),
                };
            }

            if (str_starts_with($url, 'https://api.open-meteo.com/')) {
                return Http::response([
                    'hourly' => [
                        'time' => [
                            '2026-03-14T20:00',
                            '2026-03-14T21:00',
                            '2026-03-14T22:00',
                            '2026-03-14T23:00',
                        ],
                        'relative_humidity_2m' => [50, 60, 70, 60],
                        'cloud_cover' => [10, 15, 20, 25],
                        'wind_speed_10m' => [10.8, 14.4, 18.0, 14.4],
                        'temperature_2m' => [5.0, 6.0, 7.0, 6.0],
                        'precipitation_probability' => [5, 10, 15, 10],
                    ],
                ], 200);
            }

            return Http::response([], 404);
        });

        $response = $this->actingAs($user)->getJson("/api/events/{$event->id}/viewing-forecast");

        $response
            ->assertOk()
            ->assertJsonPath('viewing_window.start_at', '2026-03-14T20:10:00+01:00')
            ->assertJsonPath('viewing_window.end_at', '2026-03-14T23:30:00+01:00')
            ->assertJsonPath('summary.clouds_pct', 25)
            ->assertJsonPath('summary.temp_c', 6)
            ->assertJsonPath('summary.wind_ms', 4)
            ->assertJsonPath('summary.humidity_pct', 60)
            ->assertJsonPath('summary.precip_pct', 15)
            ->assertJsonPath('summary.rating', 'good')
            ->assertJsonPath('summary.label_sk', 'Dobre');
    }

    public function test_peak_only_event_after_midnight_centers_window_on_same_night(): void
    {
        Cache::flush();

        $user = User::factory()->create([
            'latitude' => 48.1486,
            'longitude' => 17.1077,
            'timezone' => 'Europe/Bratislava',
            'location_label' => 'Bratislava',
            'location_source' => 'manual',
        ]);
        $event = $this->createPublishedEvent([
            'start_at' => null,
            'end_at' => null,
            'max_at' => CarbonImmutable::parse('2026-03-15 02:30:00', 'UTC'),
        ]);

        Http::fake(function ($request) {
            $url = $request->url();

            if (str_starts_with($url, 'https://aa.usno.navy.mil/')) {
                parse_str(parse_url($url, PHP_URL_QUERY) ?: '', $query);
                $date = $query['date'] ?? null;

                return match ($date) {
                    '2026-03-14' => Http::response($this->usnoPayload('06:11', '18:05', '05:39', '20:10'), 200),
                    '2026-03-15' => Http::response($this->usnoPayload('06:09', '18:06', '05:37', '20:12'), 200),
                    '2026-03-16' => Http::response($this->usnoPayload('06:07', '18:08', '05:35', '20:13'), 200),
                    default => Http::response([], 404),
                };
            }

            if (str_starts_with($url, 'https://api.open-meteo.com/')) {
                return Http::response([
                    'hourly' => [
                        'time' => [
                            '2026-03-15T01:00',
                            '2026-03-15T02:00',
                            '2026-03-15T03:00',
                            '2026-03-15T04:00',
                            '2026-03-15T05:00',
                        ],
                        'relative_humidity_2m' => [55, 58, 60, 62, 63],
                        'cloud_cover' => [12, 18, 20, 16, 14],
                        'wind_speed_10m' => [7.2, 10.8, 10.8, 7.2, 7.2],
                        'temperature_2m' => [4.0, 4.0, 3.0, 3.0, 2.0],
                        'precipitation_probability' => [5, 5, 10, 10, 10],
                    ],
                ], 200);
            }

            return Http::response([], 404);
        });

        $response = $this->actingAs($user)->getJson("/api/events/{$event->id}/viewing-forecast");

        $response
            ->assertOk()
            ->assertJsonPath('viewing_window.start_at', '2026-03-15T01:30:00+01:00')
            ->assertJsonPath('viewing_window.end_at', '2026-03-15T05:30:00+01:00');
    }

    /**
     * @param array<string,mixed> $overrides
     */
    private function createPublishedEvent(array $overrides = []): Event
    {
        return Event::query()->create(array_merge([
            'title' => 'Meteoricky roj',
            'type' => 'meteor_shower',
            'visibility' => 1,
            'start_at' => CarbonImmutable::parse('2026-03-14 18:00:00', 'UTC'),
            'end_at' => CarbonImmutable::parse('2026-03-14 22:30:00', 'UTC'),
            'max_at' => CarbonImmutable::parse('2026-03-14 20:30:00', 'UTC'),
            'short' => 'Vrchol roju.',
            'description' => 'Najlepsie po zotmeni.',
            'source_name' => 'manual',
            'source_uid' => 'manual-meteor-1',
            'source_hash' => 'manual-meteor-1',
        ], $overrides));
    }

    private function usnoPayload(string $sunrise, string $sunset, string $civilBegin, string $civilEnd): array
    {
        return [
            'properties' => [
                'data' => [
                    'curphase' => 'Waxing Gibbous',
                    'fracillum' => '76%',
                    'sundata' => [
                        ['phen' => 'Rise', 'time' => $sunrise],
                        ['phen' => 'Set', 'time' => $sunset],
                        ['phen' => 'Begin Civil Twilight', 'time' => $civilBegin],
                        ['phen' => 'End Civil Twilight', 'time' => $civilEnd],
                    ],
                ],
            ],
        ];
    }
}
