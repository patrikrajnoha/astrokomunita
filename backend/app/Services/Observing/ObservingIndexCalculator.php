<?php

namespace App\Services\Observing;

class ObservingIndexCalculator
{
    public function __construct(
        private readonly ObservingWeights $weights,
        private readonly ObservingNormalization $normalization
    ) {
    }

    /**
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public function calculate(string $mode, array $input): array
    {
        $resolvedMode = $this->weights->sanitizeMode($mode);
        $weightSet = $this->weights->forMode($resolvedMode);

        $sun = is_array($input['sun'] ?? null) ? $input['sun'] : [];
        $date = (string) ($input['date'] ?? now()->toDateString());
        $tz = (string) ($input['tz'] ?? config('observing.default_timezone', 'UTC'));
        $hourLocal = is_string($input['hour_local'] ?? null) ? $input['hour_local'] : null;

        $seeing = $this->normalization->seeingScore(
            isset($input['wind_speed_kmh']) && is_numeric($input['wind_speed_kmh']) ? (float) $input['wind_speed_kmh'] : null,
            isset($input['humidity_pct']) && is_numeric($input['humidity_pct']) ? (int) $input['humidity_pct'] : null
        );

        $factors = [
            'humidity' => $this->normalization->humidityScore(
                isset($input['humidity_pct']) && is_numeric($input['humidity_pct']) ? (int) $input['humidity_pct'] : null
            ),
            'cloud' => $this->normalization->cloudScore(
                isset($input['cloud_cover_pct']) && is_numeric($input['cloud_cover_pct']) ? (int) $input['cloud_cover_pct'] : null
            ),
            'air_quality' => $this->normalization->airQualityScore(
                isset($input['pm25']) && is_numeric($input['pm25']) ? (float) $input['pm25'] : null,
                isset($input['pm10']) && is_numeric($input['pm10']) ? (float) $input['pm10'] : null
            ),
            'moon' => $this->normalization->moonScore(
                isset($input['moon_illumination_pct']) && is_numeric($input['moon_illumination_pct']) ? (int) $input['moon_illumination_pct'] : null
            ),
            'darkness' => $this->normalization->darknessScore(
                is_string($sun['status'] ?? null) ? (string) $sun['status'] : null,
                is_string($sun['sunset'] ?? null) ? (string) $sun['sunset'] : null,
                is_string($sun['sunrise'] ?? null) ? (string) $sun['sunrise'] : null,
                is_string($sun['civil_twilight_end'] ?? null) ? (string) $sun['civil_twilight_end'] : null,
                is_string($sun['civil_twilight_begin'] ?? null) ? (string) $sun['civil_twilight_begin'] : null,
                $date,
                $tz,
                $hourLocal
            ),
            'seeing' => $seeing['score'],
        ];

        $weightedTotal = 0.0;
        $weightTotal = 0.0;
        foreach ($weightSet as $factor => $weight) {
            if (!array_key_exists($factor, $factors)) {
                continue;
            }

            $weightedTotal += $factors[$factor] * $weight;
            $weightTotal += $weight;
        }

        $index = $weightTotal > 0
            ? $this->normalization->clamp($weightedTotal / $weightTotal)
            : 0;

        $alerts = $this->buildAlerts($resolvedMode, $input, $factors);
        $alertLevel = $this->resolveAlertLevel($alerts);
        $overall = $this->buildOverall($index, $alerts, $alertLevel);

        return [
            'observing_mode' => $resolvedMode,
            'observing_index' => $index,
            'factors' => $factors,
            'weights' => $weightSet,
            'alerts' => $alerts,
            'overall' => $overall,
            'seeing' => [
                'score' => $factors['seeing'],
                'formula' => $seeing['formula'],
                'wind_speed_kmh' => isset($input['wind_speed_kmh']) && is_numeric($input['wind_speed_kmh'])
                    ? round((float) $input['wind_speed_kmh'], 1)
                    : null,
                'humidity_pct' => isset($input['humidity_pct']) && is_numeric($input['humidity_pct'])
                    ? (int) $input['humidity_pct']
                    : null,
            ],
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $hourlyPoints
     * @param array<string,mixed> $baseInput
     * @return array{series:array<int,array<string,mixed>>,best_time_local:?string,best_time_index:?int,best_time_reason:?string}
     */
    public function calculateBestTime(string $mode, array $hourlyPoints, array $baseInput): array
    {
        $series = [];

        foreach ($hourlyPoints as $point) {
            $localTime = is_string($point['local_time'] ?? null) ? $point['local_time'] : null;
            if (!$localTime || !preg_match('/^\d{2}:\d{2}$/', $localTime)) {
                continue;
            }

            $hourInput = $baseInput;
            $hourInput['hour_local'] = $localTime;
            $hourInput['humidity_pct'] = isset($point['humidity_pct']) && is_numeric($point['humidity_pct'])
                ? (int) $point['humidity_pct']
                : null;
            $hourInput['cloud_cover_pct'] = isset($point['cloud_cover_pct']) && is_numeric($point['cloud_cover_pct'])
                ? (int) $point['cloud_cover_pct']
                : null;
            $hourInput['wind_speed_kmh'] = isset($point['wind_speed_kmh']) && is_numeric($point['wind_speed_kmh'])
                ? (float) $point['wind_speed_kmh']
                : null;

            $hourResult = $this->calculate($mode, $hourInput);

            $series[] = [
                'local_time' => $localTime,
                'index' => $hourResult['observing_index'],
                'humidity_pct' => $hourInput['humidity_pct'],
                'cloud_cover_pct' => $hourInput['cloud_cover_pct'],
                'wind_speed_kmh' => $hourInput['wind_speed_kmh'],
                'moon_altitude_deg' => isset($point['moon_altitude_deg']) && is_numeric($point['moon_altitude_deg'])
                    ? round((float) $point['moon_altitude_deg'], 1)
                    : null,
                'darkness_score' => $hourResult['factors']['darkness'],
            ];
        }

        if ($series === []) {
            return [
                'series' => [],
                'best_time_local' => null,
                'best_time_index' => null,
                'best_time_reason' => null,
            ];
        }

        usort($series, static function (array $a, array $b): int {
            $indexCmp = ($b['index'] <=> $a['index']);
            if ($indexCmp !== 0) {
                return $indexCmp;
            }

            return strcmp((string) $a['local_time'], (string) $b['local_time']);
        });

        $best = $series[0];
        $reason = $this->buildBestTimeReason($best);

        usort($series, static fn (array $a, array $b): int => strcmp((string) $a['local_time'], (string) $b['local_time']));

        return [
            'series' => $series,
            'best_time_local' => $best['local_time'],
            'best_time_index' => $best['index'],
            'best_time_reason' => $reason,
        ];
    }

    /**
     * @param array<string,mixed> $input
     * @param array<string,int> $factors
     * @return array<int,array{level:string,code:string,message:string}>
     */
    private function buildAlerts(string $mode, array $input, array $factors): array
    {
        $alerts = [];

        $humidity = isset($input['humidity_pct']) && is_numeric($input['humidity_pct']) ? (int) $input['humidity_pct'] : null;
        if ($humidity !== null && $humidity >= 85) {
            $alerts[] = [
                'level' => 'warn',
                'code' => 'high_humidity',
                'message' => 'Vysoka vlhkost moze znizit kontrast objektov.',
            ];
        }

        $cloud = isset($input['cloud_cover_pct']) && is_numeric($input['cloud_cover_pct']) ? (int) $input['cloud_cover_pct'] : null;
        if ($cloud !== null && $cloud >= 80) {
            $alerts[] = [
                'level' => 'severe',
                'code' => 'high_cloud_cover',
                'message' => 'Vysoka oblacnost vyrazne obmedzuje pozorovanie.',
            ];
        }

        $pm25 = isset($input['pm25']) && is_numeric($input['pm25']) ? (float) $input['pm25'] : null;
        $pm10 = isset($input['pm10']) && is_numeric($input['pm10']) ? (float) $input['pm10'] : null;
        if (($pm25 !== null && $pm25 >= 35.0) || ($pm10 !== null && $pm10 >= 60.0)) {
            $alerts[] = [
                'level' => 'warn',
                'code' => 'air_quality',
                'message' => 'Zvysene aerosoly mozu znizit transparentnost oblohy.',
            ];
        }

        $moon = isset($input['moon_illumination_pct']) && is_numeric($input['moon_illumination_pct'])
            ? (int) $input['moon_illumination_pct']
            : null;
        if ($moon !== null && $moon >= 90 && in_array($mode, [ObservingWeights::MODE_DEEP_SKY, ObservingWeights::MODE_METEORS], true)) {
            $alerts[] = [
                'level' => 'warn',
                'code' => 'bright_moon',
                'message' => 'Jasny Mesiac znizuje viditelnost slabsich objektov.',
            ];
        }

        if (($factors['darkness'] ?? 50) <= 20) {
            $alerts[] = [
                'level' => 'severe',
                'code' => 'low_darkness',
                'message' => 'Obloha je stale prilis svetla.',
            ];
        }

        if (($factors['seeing'] ?? 50) <= 35 && $mode === ObservingWeights::MODE_PLANETS) {
            $alerts[] = [
                'level' => 'warn',
                'code' => 'poor_seeing',
                'message' => 'Seeing proxy indikuje nestabilny obraz planety.',
            ];
        }

        return $alerts;
    }

    /**
     * @param array<int,array{level:string,code:string,message:string}> $alerts
     * @return array{label:string,reason:?string,alert_level:string}
     */
    private function buildOverall(int $index, array $alerts, string $alertLevel): array
    {
        $label = match (true) {
            $index >= 75 => 'OK',
            $index >= 45 => 'Pozor',
            default => 'Zlé',
        };

        if ($alertLevel === 'severe' && $label === 'OK') {
            $label = 'Pozor';
        }

        $reason = $alerts[0]['message'] ?? match (true) {
            $index >= 75 => 'Podmienky su dobre pre pozorovanie.',
            $index >= 45 => 'Podmienky su priemerne, sleduj lokalne zmeny.',
            default => 'Podmienky su momentalne slabe.',
        };

        return [
            'label' => $label,
            'reason' => $reason,
            'alert_level' => $alertLevel,
        ];
    }

    /**
     * @param array<int,array{level:string,code:string,message:string}> $alerts
     */
    private function resolveAlertLevel(array $alerts): string
    {
        $rank = [
            'none' => 0,
            'info' => 1,
            'warn' => 2,
            'severe' => 3,
        ];

        $highest = 'none';
        $highestScore = 0;

        foreach ($alerts as $alert) {
            $candidate = is_string($alert['level'] ?? null) ? $alert['level'] : 'none';
            $score = $rank[$candidate] ?? 0;
            if ($score > $highestScore) {
                $highest = $candidate;
                $highestScore = $score;
            }
        }

        return $highest;
    }

    /**
     * @param array<string,mixed> $point
     */
    private function buildBestTimeReason(array $point): string
    {
        $reasons = [];
        if (isset($point['cloud_cover_pct']) && is_numeric($point['cloud_cover_pct']) && (int) $point['cloud_cover_pct'] <= 35) {
            $reasons[] = 'nizka oblacnost';
        }

        if (isset($point['humidity_pct']) && is_numeric($point['humidity_pct']) && (int) $point['humidity_pct'] <= 65) {
            $reasons[] = 'prijatelna vlhkost';
        }

        if (isset($point['darkness_score']) && is_numeric($point['darkness_score']) && (int) $point['darkness_score'] >= 50) {
            $reasons[] = 'dostatocna tma';
        }

        if ($reasons === []) {
            return 'Najlepsia dostupna kombinacia faktorov v ramci dneska.';
        }

        return 'Najlepsi cas: ' . implode(', ', $reasons) . '.';
    }
}
