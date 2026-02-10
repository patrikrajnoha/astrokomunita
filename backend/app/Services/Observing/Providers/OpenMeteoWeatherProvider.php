<?php

namespace App\Services\Observing\Providers;

use App\Services\Observing\Contracts\WeatherProvider;
use App\Services\Observing\Support\ObservingHttp;
use App\Services\Observing\Support\ObservingProviderException;
use DateTimeImmutable;

class OpenMeteoWeatherProvider implements WeatherProvider
{
    public function __construct(
        private readonly ObservingHttp $http
    ) {
    }

    public function get(float $lat, float $lon, string $date, string $tz, ?string $targetEveningTime = null): array
    {
        $query = [
            'latitude' => number_format($lat, 6, '.', ''),
            'longitude' => number_format($lon, 6, '.', ''),
            'timezone' => $tz,
            'current' => 'relative_humidity_2m',
            'hourly' => 'relative_humidity_2m',
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

        $hourlyTimes = data_get($payload, 'hourly.time', []);
        $hourlyHumidity = data_get($payload, 'hourly.relative_humidity_2m', []);
        $eveningPct = $this->pickClosestHumidity($date, $hourlyTimes, $hourlyHumidity, $targetEveningTime);

        if ($eveningPct === null) {
            $eveningPct = $currentPct;
        }

        return [
            'current_pct' => $currentPct,
            'evening_pct' => $eveningPct,
            'status' => ($currentPct === null && $eveningPct === null) ? 'unavailable' : 'ok',
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
}
