<?php

namespace App\Services\Observing\Providers;

use App\Services\Observing\Contracts\WeatherProvider;
use App\Services\Observing\OpenMeteoWeatherCodeMapper;
use App\Services\Observing\Support\ObservingHttp;
use App\Services\Observing\Support\ObservingProviderException;
use DateTimeInterface;
use DateTimeImmutable;
use DateTimeZone;

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
            'hourly' => 'relative_humidity_2m,cloud_cover,wind_speed_10m,temperature_2m',
            'forecast_days' => 1,
        ];

        $payload = $this->requestForecastPayload($query, $tz);
        $timezone = $this->resolveTimezone($tz);

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
        $currentAtRaw = data_get($payload, 'current.time');
        $currentAt = $this->parsePointDate($currentAtRaw, $timezone)?->format(DateTimeInterface::ATOM);
        $currentWeatherCodeRaw = data_get($payload, 'current.weather_code');
        $currentWeatherCode = is_numeric($currentWeatherCodeRaw) ? (int) round((float) $currentWeatherCodeRaw) : null;
        $currentWeatherLabelSk = $this->weatherCodeMapper->labelSk($currentWeatherCode);

        $hourlyTimes = data_get($payload, 'hourly.time', []);
        $hourlyHumidity = data_get($payload, 'hourly.relative_humidity_2m', []);
        $hourlyCloud = data_get($payload, 'hourly.cloud_cover', []);
        $hourlyWind = data_get($payload, 'hourly.wind_speed_10m', data_get($payload, 'hourly.windspeed_10m', []));
        $hourlyTemperature = data_get($payload, 'hourly.temperature_2m', []);
        $currentTargetTime = $this->resolveCurrentTargetTime($tz);

        if ($currentPct === null) {
            $currentPct = $this->pickClosestHumidity($date, $hourlyTimes, $hourlyHumidity, $currentTargetTime);
        }

        if ($currentCloudPct === null) {
            $currentCloudPct = $this->pickClosestCloudCover($date, $hourlyTimes, $hourlyCloud, $currentTargetTime);
        }

        if ($currentWindKmh === null) {
            $currentWindKmh = $this->pickClosestWindSpeed($date, $hourlyTimes, $hourlyWind, $currentTargetTime);
        }

        if ($currentTemperatureC === null) {
            $currentTemperatureC = $this->pickClosestFloatValue($date, $hourlyTimes, $hourlyTemperature, $currentTargetTime);
        }

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

        $hourlyPoints = $this->buildHourlyPoints($date, $hourlyTimes, $hourlyHumidity, $hourlyCloud, $hourlyWind, $hourlyTemperature, $tz);

        return [
            'current_pct' => $currentPct,
            'evening_pct' => $eveningPct,
            'current_cloud_pct' => $currentCloudPct,
            'evening_cloud_pct' => $eveningCloudPct,
            'current_wind_kmh' => $currentWindKmh,
            'evening_wind_kmh' => $eveningWindKmh,
            'current_temperature_c' => $currentTemperatureC,
            'current_apparent_temperature_c' => $currentApparentTemperatureC,
            'current_at' => $currentAt,
            'current_weather_code' => $currentWeatherCode,
            'current_weather_label_sk' => $currentWeatherLabelSk,
            'hourly' => $hourlyPoints,
            'status' => ($currentPct === null && $eveningPct === null && $currentCloudPct === null && $eveningCloudPct === null && $hourlyPoints === []) ? 'unavailable' : 'ok',
        ];
    }

    public function hourlyForecast(float $lat, float $lon, string $fromDate, string $toDate, string $tz): array
    {
        $query = [
            'latitude' => number_format($lat, 6, '.', ''),
            'longitude' => number_format($lon, 6, '.', ''),
            'timezone' => $tz,
            'hourly' => 'relative_humidity_2m,cloud_cover,wind_speed_10m,temperature_2m,precipitation_probability',
            'start_date' => $fromDate,
            'end_date' => $toDate,
        ];

        $payload = $this->requestForecastPayload($query, $tz);

        return $this->buildHourlyForecastPoints(
            data_get($payload, 'hourly.time', []),
            data_get($payload, 'hourly.relative_humidity_2m', []),
            data_get($payload, 'hourly.cloud_cover', []),
            data_get($payload, 'hourly.wind_speed_10m', data_get($payload, 'hourly.windspeed_10m', [])),
            data_get($payload, 'hourly.temperature_2m', []),
            data_get($payload, 'hourly.precipitation_probability', []),
            $tz
        );
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
        $value = $this->pickClosestNumericValue($date, $times, $windSpeed, $targetEveningTime);
        return $value === null ? null : round((float) $value, 1);
    }

    private function pickClosestIntValue(string $date, mixed $times, mixed $values, ?string $targetEveningTime): ?int
    {
        $value = $this->pickClosestNumericValue($date, $times, $values, $targetEveningTime);
        return $value === null ? null : (int) round($value);
    }

    private function pickClosestFloatValue(string $date, mixed $times, mixed $values, ?string $targetTime): ?float
    {
        $value = $this->pickClosestNumericValue($date, $times, $values, $targetTime);
        return $value === null ? null : round($value, 1);
    }

    private function pickClosestNumericValue(string $date, mixed $times, mixed $values, ?string $targetTime): ?float
    {
        if (!is_array($times) || !is_array($values) || count($times) === 0 || count($times) !== count($values)) {
            return null;
        }

        $target = $targetTime;
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
                $bestValue = (float) $values[$idx];
            }
        }

        return $bestValue;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function buildHourlyPoints(string $date, mixed $times, mixed $humidities, mixed $cloudCover, mixed $windSpeed, mixed $temperatures, string $tz): array
    {
        $points = array_filter(
            $this->buildHourlyForecastPoints($times, $humidities, $cloudCover, $windSpeed, $temperatures, null, $tz),
            static fn (array $point): bool => ($point['local_date'] ?? null) === $date
        );

        $normalized = array_map(static function (array $point): array {
            return [
                'local_time' => $point['local_time'] ?? null,
                'humidity_pct' => $point['humidity_pct'] ?? null,
                'cloud_cover_pct' => $point['cloud_cover_pct'] ?? null,
                'wind_speed_kmh' => $point['wind_speed_kmh'] ?? null,
            ];
        }, array_values($points));

        return array_slice($normalized, 0, 24);
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function buildHourlyForecastPoints(
        mixed $times,
        mixed $humidities,
        mixed $cloudCover,
        mixed $windSpeed,
        mixed $temperatures,
        mixed $precipitationProbability,
        string $tz
    ): array {
        if (!is_array($times) || count($times) === 0) {
            return [];
        }

        $timezone = $this->resolveTimezone($tz);
        $points = [];

        foreach ($times as $idx => $timeRaw) {
            $pointDate = $this->parsePointDate($timeRaw, $timezone);
            if (!$pointDate) {
                continue;
            }

            $points[] = [
                'at' => $pointDate->format(DateTimeInterface::ATOM),
                'local_date' => $pointDate->format('Y-m-d'),
                'local_time' => $pointDate->format('H:i'),
                'humidity_pct' => $this->normalizeNullableInt($humidities, $idx),
                'cloud_cover_pct' => $this->normalizeNullableInt($cloudCover, $idx),
                'wind_speed_kmh' => $this->normalizeNullableFloat($windSpeed, $idx),
                'temperature_c' => $this->normalizeNullableFloat($temperatures, $idx),
                'precipitation_probability_pct' => $this->normalizeNullableInt($precipitationProbability, $idx),
            ];
        }

        return $points;
    }

    private function requestForecastPayload(array $query, string $tz): array
    {
        try {
            return $this->http->getJson(
                'open_meteo',
                (string) config('observing.providers.open_meteo_url'),
                $query
            );
        } catch (ObservingProviderException $exception) {
            if ($tz !== 'UTC') {
                $query['timezone'] = 'UTC';

                return $this->http->getJson(
                    'open_meteo',
                    (string) config('observing.providers.open_meteo_url'),
                    $query
                );
            }

            throw $exception;
        }
    }

    private function resolveTimezone(string $tz): DateTimeZone
    {
        try {
            return new DateTimeZone($tz);
        } catch (\Throwable) {
            return new DateTimeZone('UTC');
        }
    }

    private function parsePointDate(mixed $timeRaw, DateTimeZone $timezone): ?DateTimeImmutable
    {
        if (!is_string($timeRaw)) {
            return null;
        }

        $normalized = trim($timeRaw);
        if ($normalized === '') {
            return null;
        }

        $parsed = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $normalized, $timezone);
        if ($parsed instanceof DateTimeImmutable) {
            return $parsed;
        }

        try {
            return new DateTimeImmutable($normalized, $timezone);
        } catch (\Throwable) {
            return null;
        }
    }

    private function resolveCurrentTargetTime(string $tz): string
    {
        try {
            return (new DateTimeImmutable('now', $this->resolveTimezone($tz)))->format('H:i');
        } catch (\Throwable) {
            return (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('H:i');
        }
    }

    private function normalizeNullableInt(mixed $values, int|string $idx): ?int
    {
        if (!is_array($values) || !isset($values[$idx]) || !is_numeric($values[$idx])) {
            return null;
        }

        return (int) round((float) $values[$idx]);
    }

    private function normalizeNullableFloat(mixed $values, int|string $idx): ?float
    {
        if (!is_array($values) || !isset($values[$idx]) || !is_numeric($values[$idx])) {
            return null;
        }

        return round((float) $values[$idx], 1);
    }
}
