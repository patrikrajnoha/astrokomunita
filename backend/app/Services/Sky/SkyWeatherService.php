<?php

namespace App\Services\Sky;

use App\Services\Observing\Contracts\WeatherProvider;
use Carbon\CarbonImmutable;

class SkyWeatherService
{
    public function __construct(
        private readonly WeatherProvider $weatherProvider
    ) {
    }

    /**
     * @return array{
     *   cloud_percent:int,
     *   wind_speed:float,
     *   wind_unit:string,
     *   humidity_percent:int,
     *   temperature_c:?float,
     *   apparent_temperature_c:?float,
     *   weather_code:?int,
     *   weather_label:?string,
     *   observing_score:int,
     *   updated_at:string,
     *   as_of:string,
     *   source:string
     * }
     */
    public function fetch(float $lat, float $lon, string $tz): array
    {
        $localDate = CarbonImmutable::now($tz)->format('Y-m-d');
        $payload = $this->weatherProvider->get($lat, $lon, $localDate, $tz, null);

        if (($payload['status'] ?? 'unavailable') !== 'ok') {
            throw new \RuntimeException('Weather provider is unavailable.');
        }

        $cloud = $this->normalizePercent($payload['current_cloud_pct'] ?? null);
        $humidity = $this->normalizePercent($payload['current_pct'] ?? null);
        $wind = $this->normalizeWind($payload['current_wind_kmh'] ?? null);
        $temperature = $this->normalizeTemperature($payload['current_temperature_c'] ?? null);
        $apparentTemperature = $this->normalizeTemperature($payload['current_apparent_temperature_c'] ?? null);
        $weatherCode = $this->normalizeWeatherCode($payload['current_weather_code'] ?? null);
        $weatherLabel = $this->normalizeWeatherLabel($payload['current_weather_label_sk'] ?? null);
        $updatedAt = $this->normalizeObservedAt($payload['current_at'] ?? null, $tz);

        if ($cloud === null && $humidity === null && $wind === null) {
            throw new \RuntimeException('Weather provider returned no usable data.');
        }

        $cloudSafe = $cloud ?? 0;
        $humiditySafe = $humidity ?? 0;
        $windSafe = $wind ?? 0.0;

        return [
            'cloud_percent' => $cloudSafe,
            'wind_speed' => round($windSafe, 1),
            'wind_unit' => 'km/h',
            'humidity_percent' => $humiditySafe,
            'temperature_c' => $temperature,
            'apparent_temperature_c' => $apparentTemperature,
            'weather_code' => $weatherCode,
            'weather_label' => $weatherLabel,
            'observing_score' => $this->calculateObservingScore($cloudSafe, $humiditySafe, $windSafe),
            'updated_at' => $updatedAt,
            'as_of' => $updatedAt,
            'source' => $this->source(),
        ];
    }

    public function source(): string
    {
        return 'open_meteo';
    }

    private function calculateObservingScore(int $cloudPercent, int $humidityPercent, float $windSpeed): int
    {
        $cloudPenalty = $this->clamp($cloudPercent, 0, 100) * 0.50;
        $humidityPenalty = $this->clamp($humidityPercent, 0, 100) * 0.30;
        $windPenalty = (min(max($windSpeed, 0.0), 50.0) / 50.0) * 20.0;

        $score = (int) round(100 - ($cloudPenalty + $humidityPenalty + $windPenalty));
        return $this->clamp($score, 0, 100);
    }

    private function normalizePercent(mixed $value): ?int
    {
        if (!is_numeric($value)) {
            return null;
        }

        $rounded = (int) round((float) $value);
        return $this->clamp($rounded, 0, 100);
    }

    private function normalizeWind(mixed $value): ?float
    {
        if (!is_numeric($value)) {
            return null;
        }

        return round(max(0.0, (float) $value), 1);
    }

    private function normalizeTemperature(mixed $value): ?float
    {
        if (!is_numeric($value)) {
            return null;
        }

        return round((float) $value, 1);
    }

    private function normalizeWeatherCode(mixed $value): ?int
    {
        if (!is_numeric($value)) {
            return null;
        }

        return (int) round((float) $value);
    }

    private function normalizeWeatherLabel(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $trimmed = trim($value);
        return $trimmed !== '' ? $trimmed : null;
    }

    private function normalizeObservedAt(mixed $value, string $tz): string
    {
        if (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed !== '') {
                try {
                    return CarbonImmutable::parse($trimmed, $tz)->setTimezone($tz)->toIso8601String();
                } catch (\Throwable) {
                    // Fallback below.
                }
            }
        }

        return CarbonImmutable::now($tz)->toIso8601String();
    }

    private function clamp(int $value, int $min, int $max): int
    {
        return max($min, min($max, $value));
    }
}
