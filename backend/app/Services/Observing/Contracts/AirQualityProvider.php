<?php

namespace App\Services\Observing\Contracts;

interface AirQualityProvider
{
    /**
     * @return array{
     *   pm25:?float,
     *   pm10:?float,
     *   source:string,
     *   status:string
     * }
     */
    public function get(float $lat, float $lon, string $date, string $tz): array;
}
