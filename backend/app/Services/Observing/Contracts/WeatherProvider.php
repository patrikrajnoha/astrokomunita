<?php

namespace App\Services\Observing\Contracts;

interface WeatherProvider
{
    /**
     * @return array{
     *   current_pct:?int,
     *   evening_pct:?int,
     *   status:string
     * }
     */
    public function fetch(float $lat, float $lon, string $date, string $tz, ?string $targetEveningTime = null): array;
}

