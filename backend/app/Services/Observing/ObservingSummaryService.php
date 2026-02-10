<?php

namespace App\Services\Observing;

use App\Services\Observing\Contracts\AirQualityProvider;
use App\Services\Observing\Contracts\SunMoonProvider;
use App\Services\Observing\Contracts\WeatherProvider;
use Carbon\CarbonImmutable;

class ObservingSummaryService
{
    public function __construct(
        private readonly SunMoonProvider $sunMoonProvider,
        private readonly WeatherProvider $weatherProvider,
        private readonly AirQualityProvider $airQualityProvider
    ) {
    }

    /**
     * @return array{summary:array<string,mixed>,is_partial:bool}
     */
    public function buildSummary(float $lat, float $lon, string $date, string $tz): array
    {
        $sun = [
            'sunrise' => null,
            'sunset' => null,
            'civil_twilight_begin' => null,
            'civil_twilight_end' => null,
            'status' => 'unavailable',
        ];

        $moon = [
            'phase_name' => null,
            'illumination_pct' => null,
            'warning' => null,
            'status' => 'unavailable',
        ];

        $humidity = [
            'current_pct' => null,
            'evening_pct' => null,
            'label' => 'Nedostupne',
            'note' => null,
            'status' => 'unavailable',
        ];

        $airQuality = [
            'pm25' => null,
            'pm10' => null,
            'label' => 'Nedostupne',
            'note' => null,
            'source' => 'OpenAQ',
            'status' => 'unavailable',
        ];

        try {
            $sunMoonRaw = $this->sunMoonProvider->fetch($lat, $lon, $date, $tz);
            $sun = [
                'sunrise' => $sunMoonRaw['sunrise'] ?? null,
                'sunset' => $sunMoonRaw['sunset'] ?? null,
                'civil_twilight_begin' => $sunMoonRaw['civil_twilight_begin'] ?? null,
                'civil_twilight_end' => $sunMoonRaw['civil_twilight_end'] ?? null,
                'status' => $sunMoonRaw['status'] ?? 'unavailable',
            ];

            $illuminationPct = $this->normalizeIlluminationPct($sunMoonRaw['fracillum'] ?? null);
            $moon = [
                'phase_name' => $sunMoonRaw['phase_name'] ?? null,
                'illumination_pct' => $illuminationPct,
                'warning' => ObservingHeuristics::moonWarning($illuminationPct),
                'status' => (($sunMoonRaw['phase_name'] ?? null) || $illuminationPct !== null) ? 'ok' : 'unavailable',
            ];
        } catch (\Throwable) {
            // Keep section unavailable but keep endpoint healthy.
        }

        try {
            $targetEveningTime = $this->resolveTargetEveningTime($sun['civil_twilight_end']);
            $weatherRaw = $this->weatherProvider->fetch($lat, $lon, $date, $tz, $targetEveningTime);
            $humidityHeuristic = ObservingHeuristics::humidity($weatherRaw['evening_pct'] ?? $weatherRaw['current_pct'] ?? null);

            $humidity = [
                'current_pct' => $weatherRaw['current_pct'] ?? null,
                'evening_pct' => $weatherRaw['evening_pct'] ?? null,
                'label' => $humidityHeuristic['label'],
                'note' => $humidityHeuristic['note'],
                'status' => $weatherRaw['status'] ?? $humidityHeuristic['status'],
            ];
        } catch (\Throwable) {
            // Keep section unavailable but keep endpoint healthy.
        }

        try {
            $airRaw = $this->airQualityProvider->fetch($lat, $lon);
            $airHeuristic = ObservingHeuristics::airQuality($airRaw['pm25'] ?? null, $airRaw['pm10'] ?? null);

            $airQuality = [
                'pm25' => $airRaw['pm25'] ?? null,
                'pm10' => $airRaw['pm10'] ?? null,
                'label' => $airHeuristic['label'],
                'note' => $airHeuristic['note'],
                'source' => $airRaw['source'] ?? 'OpenAQ',
                'status' => $airRaw['status'] ?? $airHeuristic['status'],
            ];
        } catch (\Throwable) {
            // Keep section unavailable but keep endpoint healthy.
        }

        $overallLabels = [
            $humidity['label'],
            $airQuality['label'],
        ];

        if ($moon['warning']) {
            $overallLabels[] = 'Pozor';
        }

        $summary = [
            'location' => [
                'lat' => round($lat, 6),
                'lon' => round($lon, 6),
                'tz' => $tz,
            ],
            'date' => $date,
            'sun' => $sun,
            'moon' => $moon,
            'atmosphere' => [
                'humidity' => $humidity,
                'air_quality' => $airQuality,
            ],
            'overall' => [
                'label' => ObservingHeuristics::overallLabel($overallLabels),
            ],
            'updated_at' => CarbonImmutable::now()->toIso8601String(),
        ];

        $isPartial = in_array('unavailable', [
            $sun['status'],
            $moon['status'],
            $humidity['status'],
            $airQuality['status'],
        ], true);

        return [
            'summary' => $summary,
            'is_partial' => $isPartial,
        ];
    }

    private function normalizeIlluminationPct(mixed $fraction): ?int
    {
        if ($fraction === null || !is_numeric($fraction)) {
            return null;
        }

        return (int) round(max(0, min(1, (float) $fraction)) * 100);
    }

    private function resolveTargetEveningTime(?string $civilTwilightEnd): string
    {
        if (!is_string($civilTwilightEnd) || !preg_match('/^(?<h>\d{2}):(?<m>\d{2})$/', $civilTwilightEnd, $match)) {
            return (string) config('observing.defaults.evening_target_time', '21:00');
        }

        $hour = (int) $match['h'];
        $minute = (int) $match['m'];
        $base = CarbonImmutable::create(2000, 1, 1, $hour, $minute, 0, 'UTC');
        return $base->addHour()->format('H:i');
    }
}

