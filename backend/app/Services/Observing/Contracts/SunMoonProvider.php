<?php

namespace App\Services\Observing\Contracts;

interface SunMoonProvider
{
    /**
     * @return array{
     *   sunrise:?string,
     *   sunset:?string,
     *   civil_twilight_begin:?string,
     *   civil_twilight_end:?string,
     *   status:string,
     *   phase_name:?string,
     *   fracillum:?float
     * }
     */
    public function fetch(float $lat, float $lon, string $date, string $tz): array;
}

