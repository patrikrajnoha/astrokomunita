<?php

namespace App\Services\Observing;

class ObservingHeuristics
{
    public static function humidity(?int $value): array
    {
        if ($value === null) {
            return [
                'label' => 'Nedostupne',
                'note' => null,
                'status' => 'unavailable',
            ];
        }

        if ($value < (int) config('observing.thresholds.humidity.ok_max', 60)) {
            return [
                'label' => 'OK',
                'note' => 'Nizsia vlhkost zvacsa znamena lepsiu priehladnost.',
                'status' => 'ok',
            ];
        }

        if ($value <= (int) config('observing.thresholds.humidity.warn_max', 80)) {
            return [
                'label' => 'Pozor',
                'note' => 'Vyssia vlhkost moze zhorsit kontrast a rastie riziko rosy.',
                'status' => 'ok',
            ];
        }

        return [
            'label' => 'Zle',
            'note' => 'Velmi vysoka vlhkost casto vyrazne znizuje priehladnost.',
            'status' => 'ok',
        ];
    }

    public static function airQuality(?float $pm25, ?float $pm10): array
    {
        if ($pm25 === null && $pm10 === null) {
            return [
                'label' => 'Nedostupne',
                'note' => null,
                'status' => 'unavailable',
            ];
        }

        if ($pm25 !== null) {
            if ($pm25 < (float) config('observing.thresholds.pm25.ok_max', 15)) {
                return self::airResponse('OK');
            }
            if ($pm25 <= (float) config('observing.thresholds.pm25.warn_max', 35)) {
                return self::airResponse('Pozor');
            }
            return self::airResponse('Zle');
        }

        if ($pm10 < (float) config('observing.thresholds.pm10.ok_max', 30)) {
            return self::airResponse('OK');
        }
        if ($pm10 <= (float) config('observing.thresholds.pm10.warn_max', 60)) {
            return self::airResponse('Pozor');
        }

        return self::airResponse('Zle');
    }

    public static function moonWarning(?int $illuminationPct): ?string
    {
        if ($illuminationPct === null) {
            return null;
        }

        if ($illuminationPct >= (int) config('observing.thresholds.moon.warning_min_pct', 90)) {
            return 'Mesiac je velmi jasny, slabsie objekty budu horsie viditelne.';
        }

        return null;
    }

    public static function overallLabel(array $labels): string
    {
        $priority = [
            'OK' => 1,
            'Pozor' => 2,
            'Zle' => 3,
            'Nedostupne' => 0,
        ];

        $worstLabel = 'Nedostupne';
        $worstScore = 0;

        foreach ($labels as $label) {
            $score = $priority[$label] ?? 0;
            if ($score > $worstScore) {
                $worstScore = $score;
                $worstLabel = $label;
            }
        }

        return $worstLabel;
    }

    private static function airResponse(string $label): array
    {
        return [
            'label' => $label,
            'note' => 'Vyssie aerosoly znizuju transparentnost oblohy.',
            'status' => 'ok',
        ];
    }
}

