<?php

namespace App\Services\Observing\Providers;

use App\Services\Observing\Contracts\WeatherProvider;
use App\Services\Observing\OpenMeteoWeatherCodeMapper;
use App\Services\Observing\Support\ObservingHttp;
use App\Services\Observing\Support\ObservingProviderException;
use DateTimeImmutable;

class OpenMeteoWeatherProvider implements WeatherProvider
{
    public function __construct(
        private readonly ObservingHttp $http,
        private readonly OpenMeteoWeatherCodeMapper $weatherCodeMapper
    ) {
    }

    public function get(float $lat, float $lon, string $date, string $tz, ?string $targetEveningTime = null): array
    {
        $query = [
            'latitude' => number_format($lat, 6, '.', ''),
            'longitude' => number_format($lon, 6, '.', ''),
            'timezone' => $tz,
            'current' => 'relative_humidity_2m,cloud_cover,wind_speed_10m,temperature_2m,apparent_temperature,weather_code',
            'hourly' => 'relative_humidity_2m,cloud_cover,wind_speed_10m',
            'forecast_days' => 1,
        ];

        try {
            $payload = $this->http->getJson(
                'open_meteo',
                (string) config('observing.providers.open_meteo_url'),
                $query
            );
        } catch (ObservingProviderException $exception) {
            if ($tz !== 'UTC') {
                $query['timezone'] = 'UTC';
                $payload = $this->http->getJson(
                    'open_meteo',
                    (string) config('observing.providers.open_meteo_url'),
                    $query
                );
            } else {
                throw $exception;
            }
        }

        $current = data_get($payload, 'current.relative_humidity_2m');
        $currentPct = is_numeric($current) ? (int) round((float) $current) : null;
        $currentCloud = data_get($payload, 'current.cloud_cover');
        $currentCloudPct = is_numeric($currentCloud) ? (int) round((float) $currentCloud) : null;
        $currentWindRaw = data_get($payload, 'current.wind_speed_10m', data_get($payload, 'current.windspeed_10m'));
        $currentWindKmh = is_numeric($currentWindRaw) ? round((float) $currentWindRaw, 1) : null;
        $currentTemperatureRaw = data_get($payload, 'current.temperature_2m');
        $currentTemperatureC = is_numeric($currentTemperatureRaw) ? round((float) $currentTemperatureRaw, 1) : null;
        $currentApparentTemperatureRaw = data_get($payload, 'current.apparent_temperature');
        $currentApparentTemperatureC = is_numeric($currentApparentTemperatureRaw) ? round((float) $currentApparentTemperatureRaw, 1) : null;
        $currentWeatherCodeRaw = data_get($payload, 'current.weather_code');
        $currentWeatherCode = is_numeric($currentWeatherCodeRaw) ? (int) round((float) $currentWeatherCodeRaw) : null;
        $currentWeatherLabelSk = $this->weatherCodeMapper->labelSk($currentWeatherCode);

        $hourlyTimes = data_get($payload, 'hourly.time', []);
        $hourlyHumidity = data_get($payload, 'hourly.relative_humidity_2m', []);
        $hourlyCloud = data_get($payload, 'hourly.cloud_cover', []);
        $hourlyWind = data_get($payload, 'hourly.wind_speed_10m', data_get($payload, 'hourly.windspeed_10m', []));
        $eveningPct = $this->pickClosestHumidity($date, $hourlyTimes, $hourlyHumidity, $targetEveningTime);
        $eveningCloudPct = $this->pickClosestCloudCover($date, $hourlyTimes, $hourlyCloud, $targetEveningTime);
        $eveningWindKmh = $this->pickClosestWindSpeed($date, $hourlyTimes, $hourlyWind, $targetEveningTime);

        if ($eveningPct === null) {
            $eveningPct = $currentPct;
        }

        if ($eveningCloudPct === null) {
            $eveningCloudPct = $currentCloudPct;
        }

        if ($eveningWindKmh === null) {
            $eveningWindKmh = $currentWindKmh;
        }

        $hourlyPoints = $this->buildHourlyPoints($date, $hourlyTimes, $hourlyHumidity, $hourlyCloud, $hourlyWind);

        return [
            'current_pct' => $currentPct,
            'evening_pct' => $eveningPct,
            'current_cloud_pct' => $currentCloudPct,
            'evening_cloud_pct' => $eveningCloudPct,
            'current_wind_kmh' => $currentWindKmh,
            'evening_wind_kmh' => $eveningWindKmh,
            'current_temperature_c' => $currentTemperatureC,
            'current_apparent_temperature_c' => $currentApparentTemperatureC,
            'current_weather_code' => $currentWeatherCode,
            'current_weather_label_sk' => $currentWeatherLabelSk,
            'hourly' => $hourlyPoints,
            'status' => ($currentPct === null && $eveningPct === null && $currentCloudPct === null && $eveningCloudPct === null && $hourlyPoints === []) ? 'unavailable' : 'ok',
        ];
    }

    private function pickClosestHumidity(string $date, mixed $times, mixed $humidities, ?string $targetEveningTime): ?int
    {
        if (!is_array($times) || !is_array($humidities) || count($times) === 0 || count($times) !== count($humidities)) {
            return null;
        }

        $target = $targetEveningTime;
        if (!is_string($target) || !preg_match('/^\d{2}:\d{2}$/', $target)) {
            $target = '21:00';
        }

        $targetDate = DateTimeImmutable::createFromFormat('Y-m-d H:i', "{$date} {$target}");
        if (!$targetDate) {
            return null;
        }

        $bestValue = null;
        $bestDelta = null;

        foreach ($times as $idx => $timeRaw) {
            if (!isset($humidities[$idx]) || !is_numeric($humidities[$idx]) || !is_string($timeRaw)) {
                continue;
            }

            try {
                $pointDate = new DateTimeImmutable($timeRaw);
            } catch (\Throwable) {
                continue;
            }

            $delta = abs($pointDate->getTimestamp() - $targetDate->getTimestamp());

            if ($bestDelta === null || $delta < $bestDelta) {
                $bestDelta = $delta;
                $bestValue = (int) round((float) $humidities[$idx]);
            }
        }

        return $bestValue;
    }

    private function pickClosestCloudCover(string $date, mixed $times, mixed $cloudCover, ?string $targetEveningTime): ?int
    {
        return $this->pickClosestIntValue($date, $times, $cloudCover, $targetEveningTime);
    }

    private function pickClosestWindSpeed(string $date, mixed $times, mixed $windSpeed, ?string $targetEveningTime): ?float
    {
        $value = $this->pickClosestIntValue($date, $times, $windSpeed, $targetEveningTime);
        return $value === null ? null : round((float) $value, 1);
    }

    private function pickClosestIntValue(string $date, mixed $times, mixed $values, ?string $targetEveningTime): ?int
    {
        if (!is_array($times) || !is_array($values) || count($times) === 0 || count($times) !== count($values)) {
            return null;
        }

        $target = $targetEveningTime;
        if (!is_string($target) || !preg_match('/^\d{2}:\d{2}$/', $target)) {
            $target = '21:00';
        }

        $targetDate = DateTimeImmutable::createFromFormat('Y-m-d H:i', "{$date} {$target}");
        if (!$targetDate) {
            return null;
        }

        $bestValue = null;
        $bestDelta = null;

        foreach ($times as $idx => $timeRaw) {
            if (!isset($values[$idx]) || !is_numeric($values[$idx]) || !is_string($timeRaw)) {
                continue;
            }

            try {
                $pointDate = new DateTimeImmutable($timeRaw);
            } catch (\Throwable) {
                continue;
            }

            $delta = abs($pointDate->getTimestamp() - $targetDate->getTimestamp());

            if ($bestDelta === null || $delta < $bestDelta) {
                $bestDelta = $delta;
                $bestValue = (int) round((float) $values[$idx]);
            }
        }

        return $bestValue;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function buildHourlyPoints(string $date, mixed $times, mixed $humidities, mixed $cloudCover, mixed $windSpeed): array
    {
        if (!is_array($times) || count($times) === 0) {
            return [];
        }

        $points = [];

        foreach ($times as $idx => $timeRaw) {
            if (!is_string($timeRaw)) {
                continue;
            }

            try {
                $pointDate = new DateTimeImmutable($timeRaw);
            } catch (\Throwable) {
                continue;
            }

            if ($pointDate->format('Y-m-d') !== $date) {
                continue;
            }

            $humidity = is_array($humidities) && isset($humidities[$idx]) && is_numeric($humidities[$idx])
                ? (int) round((float) $humidities[$idx])
                : null;
            $cloud = is_array($cloudCover) && isset($cloudCover[$idx]) && is_numeric($cloudCover[$idx])
                ? (int) round((float) $cloudCover[$idx])
                : null;
            $wind = is_array($windSpeed) && isset($windSpeed[$idx]) && is_numeric($windSpeed[$idx])
                ? round((float) $windSpeed[$idx], 1)
                : null;

            $points[] = [
                'local_time' => $pointDate->format('H:i'),
                'humidity_pct' => $humidity,
                'cloud_cover_pct' => $cloud,
                'wind_speed_kmh' => $wind,
            ];
        }

        return array_slice($points, 0, 24);
    }
}
