<?php

namespace App\Services\Observing\Providers;

use App\Services\Observing\Contracts\WeatherProvider;
use DateTimeImmutable;
use Illuminate\Support\Facades\Http;

class OpenMeteoWeatherProvider implements WeatherProvider
{
    public function fetch(float $lat, float $lon, string $date, string $tz, ?string $targetEveningTime = null): array
    {
        $response = Http::timeout((int) config('observing.http.timeout_seconds', 8))
            ->retry((int) config('observing.http.retry_times', 2), (int) config('observing.http.retry_sleep_ms', 200))
            ->acceptJson()
            ->get(config('observing.providers.open_meteo_url'), [
                'latitude' => $lat,
                'longitude' => $lon,
                'timezone' => $tz,
                'current' => 'relative_humidity_2m',
                'hourly' => 'relative_humidity_2m',
                'start_date' => $date,
                'end_date' => $date,
            ]);

        if (!$response->successful()) {
            throw new \RuntimeException('Open-Meteo provider request failed.');
        }

        $payload = $response->json();

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

