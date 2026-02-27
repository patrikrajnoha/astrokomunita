<?php

namespace App\Services\Observing;

class OpenMeteoWeatherCodeMapper
{
    public function labelSk(?int $weatherCode): string
    {
        if ($weatherCode === null) {
            return 'Neznáme';
        }

        return match (true) {
            $weatherCode === 0 => 'Jasno',
            in_array($weatherCode, [1], true) => 'Prevažne jasno',
            in_array($weatherCode, [2], true) => 'Polojasno',
            in_array($weatherCode, [3], true) => 'Zamračené',
            in_array($weatherCode, [45, 48], true) => 'Hmla',
            in_array($weatherCode, [51, 53, 55, 56, 57], true) => 'Mrholenie',
            in_array($weatherCode, [61, 63, 65, 66, 67, 80, 81, 82], true) => 'Dážď',
            in_array($weatherCode, [71, 73, 75, 77, 85, 86], true) => 'Sneženie',
            in_array($weatherCode, [95, 96, 99], true) => 'Búrka',
            default => 'Neznáme',
        };
    }
}
