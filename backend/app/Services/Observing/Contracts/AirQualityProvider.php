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
    public function fetch(float $lat, float $lon): array;
}

