<?php

namespace App\Services\Observing;

use Carbon\CarbonImmutable;

class ObservingNormalization
{
    public function humidityScore(?int $humidityPct): int
    {
        if ($humidityPct === null) {
            return 50;
        }

        return $this->clamp(100 - $humidityPct);
    }

    public function cloudScore(?int $cloudCoverPct): int
    {
        if ($cloudCoverPct === null) {
            return 50;
        }

        return $this->clamp(100 - $cloudCoverPct);
    }

    public function moonScore(?int $illuminationPct): int
    {
        if ($illuminationPct === null) {
            return 50;
        }

        return $this->clamp(100 - $illuminationPct);
    }

    public function airQualityScore(?float $pm25, ?float $pm10): int
    {
        if ($pm25 === null && $pm10 === null) {
            return 50;
        }

        $parts = [];

        if ($pm25 !== null) {
            $parts[] = $this->linearRangeScore($pm25, 5.0, 75.0);
        }

        if ($pm10 !== null) {
            $parts[] = $this->linearRangeScore($pm10, 10.0, 150.0);
        }

        if ($parts === []) {
            return 50;
        }

        return (int) round(array_sum($parts) / count($parts));
    }

    public function lightPollutionScore(?int $bortleClass): int
    {
        if ($bortleClass === null) {
            return 50;
        }

        // Piecewise mapping: mild penalty for Bortle 1-5, steeper from 6+.
        $normalized = (max(1, min(9, $bortleClass)) - 1) / 8; // 0..1
        $breakpoint = 4 / 8; // Bortle 5

        if ($bortleClass <= 5) {
            $factor = 0.6 * $normalized;
        } else {
            $factor = (0.6 * $breakpoint) + (1.6 * ($normalized - $breakpoint));
        }

        return $this->clamp(100 * (1 - max(0, min(1, $factor))));
    }

    public function darknessScore(
        ?string $sunStatus,
        ?string $sunset,
        ?string $sunrise,
        ?string $civilTwilightEnd,
        ?string $civilTwilightBegin,
        string $date,
        string $tz,
        ?string $hourLocal = null
    ): int {
        if ($sunStatus === 'continuous_night') {
            return 100;
        }

        if ($sunStatus === 'continuous_day') {
            return 0;
        }

        $hour = is_string($hourLocal) && preg_match('/^\d{2}:\d{2}$/', $hourLocal) ? $hourLocal : '21:00';
        $point = $this->parseDateTime("{$date} {$hour}", $tz);
        if (!$point) {
            return 50;
        }

        $civilEnd = $this->parseDateTimeFromTime($civilTwilightEnd, $date, $tz);
        $civilBegin = $this->parseDateTimeFromTime($civilTwilightBegin, $date, $tz, true);
        if ($civilEnd && $civilBegin && $point->betweenIncluded($civilEnd, $civilBegin)) {
            return 100;
        }

        $sunsetAt = $this->parseDateTimeFromTime($sunset, $date, $tz);
        $sunriseAt = $this->parseDateTimeFromTime($sunrise, $date, $tz, true);
        if ($sunsetAt && $sunriseAt && $point->betweenIncluded($sunsetAt, $sunriseAt)) {
            return 50;
        }

        return 0;
    }

    /**
     * @return array{score:int,formula:string}
     */
    public function seeingScore(?float $windSpeedKmh, ?int $humidityPct): array
    {
        if ($windSpeedKmh === null && $humidityPct === null) {
            return [
                'score' => 50,
                'formula' => 'fallback=50 (missing wind/humidity)',
            ];
        }

        $wind = max(0.0, (float) ($windSpeedKmh ?? 0.0));
        $humidity = max(0, min(100, (int) ($humidityPct ?? 0)));

        $windPenalty = min(40.0, $wind) * 1.4;
        $humidityPenalty = $humidity * 0.35;
        $raw = 100 - $windPenalty - $humidityPenalty;

        return [
            'score' => $this->clamp($raw),
            'formula' => sprintf(
                '100 - min(40,wind_kmh)*1.4 - humidity_pct*0.35 = %.1f',
                $raw
            ),
        ];
    }

    public function clamp(float|int $value, int $min = 0, int $max = 100): int
    {
        return (int) max($min, min($max, round((float) $value)));
    }

    private function linearRangeScore(float $value, float $bestMax, float $worstMin): int
    {
        if ($value <= $bestMax) {
            return 100;
        }

        if ($value >= $worstMin) {
            return 0;
        }

        $position = ($value - $bestMax) / max(0.0001, ($worstMin - $bestMax));
        return $this->clamp(100 - ($position * 100));
    }

    private function parseDateTime(string $dateTime, string $tz): ?CarbonImmutable
    {
        try {
            return CarbonImmutable::parse($dateTime, $tz);
        } catch (\Throwable) {
            return null;
        }
    }

    private function parseDateTimeFromTime(?string $time, string $date, string $tz, bool $nextDay = false): ?CarbonImmutable
    {
        if (!is_string($time) || !preg_match('/^\d{2}:\d{2}$/', $time)) {
            return null;
        }

        $base = $this->parseDateTime("{$date} {$time}", $tz);
        if (!$base) {
            return null;
        }

        return $nextDay ? $base->addDay() : $base;
    }
}
