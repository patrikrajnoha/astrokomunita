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
     *   observing_score:int,
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

        $cloud = $this->normalizePercent($payload['current_cloud_pct'] ?? $payload['evening_cloud_pct'] ?? null);
        $humidity = $this->normalizePercent($payload['current_pct'] ?? $payload['evening_pct'] ?? null);
        $wind = $this->normalizeWind($payload['current_wind_kmh'] ?? $payload['evening_wind_kmh'] ?? null);

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
            'observing_score' => $this->calculateObservingScore($cloudSafe, $humiditySafe, $windSafe),
            'as_of' => CarbonImmutable::now($tz)->toIso8601String(),
            'source' => 'open_meteo',
        ];
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

    private function clamp(int $value, int $min, int $max): int
    {
        return max($min, min($max, $value));
    }
}
