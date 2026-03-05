<?php

namespace App\Services\Observing\Contracts;

interface WeatherProvider
{
    /**
     * @return array{
     *   current_pct:?int,
     *   evening_pct:?int,
     *   current_cloud_pct:?int,
     *   evening_cloud_pct:?int,
     *   current_wind_kmh:?float,
     *   evening_wind_kmh:?float,
     *   current_temperature_c:?float,
     *   current_apparent_temperature_c:?float,
     *   current_at:?string,
     *   current_weather_code:?int,
     *   current_weather_label_sk:string,
     *   hourly:array<int,array<string,mixed>>,
     *   status:string
     * }
     */
    public function get(float $lat, float $lon, string $date, string $tz, ?string $targetEveningTime = null): array;

    /**
     * @return array<int,array{
     *   at:string,
     *   local_date:string,
     *   local_time:string,
     *   humidity_pct:?int,
     *   cloud_cover_pct:?int,
     *   wind_speed_kmh:?float,
     *   temperature_c:?float,
     *   precipitation_probability_pct:?int
     * }>
     */
    public function hourlyForecast(float $lat, float $lon, string $fromDate, string $toDate, string $tz): array;
}
