<?php

namespace App\Services\Sky;

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Factory as HttpFactory;

class SkySpaceWeatherService
{
    public function __construct(
        private readonly HttpFactory $http
    ) {
    }

    /**
     * @return array{
     *   available:bool,
     *   kp_index:float|null,
     *   estimated_kp:float|null,
     *   geomagnetic_level:string,
     *   noaa_scale:string,
     *   updated_at:string|null,
     *   observed_at:string|null,
     *   aurora:array<string,mixed>,
     *   source:array<string,mixed>,
     *   sources:array<string,string>,
     *   reason?:string
     * }
     */
    public function fetch(float $lat, float $lon, string $tz): array
    {
        $kp = $this->fetchLatestKp($tz);
        $aurora = $this->fetchAuroraWatch($lat, $lon, $tz);

        if ($kp === null && $aurora === null) {
            return $this->basePayload(false) + [
                'kp_index' => null,
                'estimated_kp' => null,
                'geomagnetic_level' => 'Nezname',
                'noaa_scale' => 'Bez dat',
                'updated_at' => null,
                'observed_at' => null,
                'aurora' => [
                    'available' => false,
                    'reason' => 'aurora_forecast_unavailable',
                ],
                'reason' => 'space_weather_unavailable',
            ];
        }

        $observedAt = $kp['observed_at'] ?? null;
        $updatedAt = $this->latestTimestamp(
            $kp['observed_at'] ?? null,
            $aurora['observed_at'] ?? null
        ) ?? ($aurora['forecast_for'] ?? null);
        $kpValue = $kp['kp_index'] ?? $kp['estimated_kp'] ?? null;

        return $this->basePayload(true) + [
            'kp_index' => $kp['kp_index'] ?? null,
            'estimated_kp' => $kp['estimated_kp'] ?? null,
            'geomagnetic_level' => $this->geomagneticLevel($kpValue),
            'noaa_scale' => $this->noaaScale($kpValue),
            'updated_at' => $updatedAt,
            'observed_at' => $observedAt,
            'aurora' => $aurora ?? [
                'available' => false,
                'reason' => 'aurora_forecast_unavailable',
            ],
        ];
    }

    /**
     * @return array{kp_index:float|null,estimated_kp:float|null,observed_at:string|null}|null
     */
    private function fetchLatestKp(string $tz): ?array
    {
        $url = trim((string) config(
            'observing.providers.swpc_planetary_k_index_url',
            'https://services.swpc.noaa.gov/json/planetary_k_index_1m.json'
        ));
        if ($url === '') {
            return null;
        }

        try {
            $response = $this->http
                ->timeout($this->timeoutSeconds())
                ->acceptJson()
                ->get($url);
        } catch (\Throwable) {
            return null;
        }

        if (!$response->successful()) {
            return null;
        }

        $payload = $response->json();
        if (!is_array($payload)) {
            return null;
        }

        for ($index = count($payload) - 1; $index >= 0; $index--) {
            $row = is_array($payload[$index] ?? null) ? $payload[$index] : null;
            if ($row === null) {
                continue;
            }

            $kpIndex = $this->toNullableFloat($row['kp_index'] ?? null);
            $estimatedKp = $this->toNullableFloat($row['estimated_kp'] ?? null);
            if ($kpIndex === null && $estimatedKp === null) {
                continue;
            }

            return [
                'kp_index' => $kpIndex,
                'estimated_kp' => $estimatedKp,
                'observed_at' => $this->toIso8601($row['time_tag'] ?? null, $tz, assumeUtc: true),
            ];
        }

        return null;
    }

    /**
     * @return array<string,mixed>|null
     */
    private function fetchAuroraWatch(float $lat, float $lon, string $tz): ?array
    {
        $url = trim((string) config(
            'observing.providers.swpc_aurora_latest_url',
            'https://services.swpc.noaa.gov/json/ovation_aurora_latest.json'
        ));
        if ($url === '') {
            return null;
        }

        try {
            $response = $this->http
                ->timeout($this->timeoutSeconds())
                ->acceptJson()
                ->get($url);
        } catch (\Throwable) {
            return null;
        }

        if (!$response->successful()) {
            return null;
        }

        $payload = $response->json();
        if (!is_array($payload)) {
            return null;
        }

        $coordinates = is_array($payload['coordinates'] ?? null) ? $payload['coordinates'] : [];
        $nearest = $this->nearestAuroraCell($coordinates, $lat, $lon);
        $corridor = $this->polewardCorridorPeak($coordinates, $lat, $lon);

        $watchScore = max(
            (int) ($nearest['score'] ?? 0),
            (int) ($corridor['score'] ?? 0)
        );

        return [
            'available' => true,
            'watch_score' => $watchScore,
            'watch_label' => $this->auroraWatchLabel($watchScore),
            // Inference: the corridor peak approximates visibility on the poleward horizon.
            'corridor_peak_score' => $corridor['score'] ?? $watchScore,
            'nearest_score' => $nearest['score'] ?? null,
            'forecast_for' => $this->toIso8601($payload['Forecast Time'] ?? null, $tz),
            'observed_at' => $this->toIso8601($payload['Observation Time'] ?? null, $tz),
            'data_format' => is_string($payload['Data Format'] ?? null)
                ? trim((string) $payload['Data Format'])
                : null,
            'inference' => 'poleward_corridor_peak',
        ];
    }

    /**
     * @param  array<int,mixed>  $coordinates
     * @return array{score:int}|null
     */
    private function nearestAuroraCell(array $coordinates, float $lat, float $lon): ?array
    {
        $best = null;
        $bestDistance = null;

        foreach ($coordinates as $row) {
            $normalized = $this->normalizeAuroraCell($row);
            if ($normalized === null) {
                continue;
            }

            $distance = hypot(
                $normalized['lat'] - $lat,
                $this->longitudeDistance($normalized['lon'], $lon)
            );

            if ($bestDistance === null || $distance < $bestDistance) {
                $best = ['score' => $normalized['score']];
                $bestDistance = $distance;
            }
        }

        return $best;
    }

    /**
     * @param  array<int,mixed>  $coordinates
     * @return array{score:int}|null
     */
    private function polewardCorridorPeak(array $coordinates, float $lat, float $lon): ?array
    {
        $best = null;
        $corridorWidthDeg = 8.0;
        $northernHemisphere = $lat >= 0;

        foreach ($coordinates as $row) {
            $normalized = $this->normalizeAuroraCell($row);
            if ($normalized === null) {
                continue;
            }

            $isPoleward = $northernHemisphere
                ? $normalized['lat'] >= floor($lat)
                : $normalized['lat'] <= ceil($lat);
            if (!$isPoleward) {
                continue;
            }

            if ($this->longitudeDistance($normalized['lon'], $lon) > $corridorWidthDeg) {
                continue;
            }

            if ($best === null || $normalized['score'] > $best['score']) {
                $best = ['score' => $normalized['score']];
            }
        }

        return $best;
    }

    /**
     * @param  mixed  $row
     * @return array{lat:float,lon:float,score:int}|null
     */
    private function normalizeAuroraCell(mixed $row): ?array
    {
        if (!is_array($row) || count($row) < 3) {
            return null;
        }

        $lon = $this->toNullableFloat($row[0] ?? null);
        $lat = $this->toNullableFloat($row[1] ?? null);
        $score = $this->toNullableFloat($row[2] ?? null);

        if ($lon === null || $lat === null || $score === null) {
            return null;
        }

        return [
            'lon' => $this->normalizeLongitude($lon),
            'lat' => $lat,
            'score' => (int) round(max(0.0, min(100.0, $score))),
        ];
    }

    private function geomagneticLevel(?float $kpValue): string
    {
        if ($kpValue === null) {
            return 'Nezname';
        }

        if ($kpValue < 4.0) {
            return 'Pokojne';
        }

        if ($kpValue < 5.0) {
            return 'Aktivne';
        }

        if ($kpValue < 6.0) {
            return 'Mensia burka';
        }

        if ($kpValue < 7.0) {
            return 'Stredna burka';
        }

        if ($kpValue < 8.0) {
            return 'Silna burka';
        }

        if ($kpValue < 9.0) {
            return 'Velmi silna burka';
        }

        return 'Extremna burka';
    }

    private function noaaScale(?float $kpValue): string
    {
        if ($kpValue === null) {
            return 'Bez dat';
        }

        if ($kpValue < 5.0) {
            return 'Pod G1';
        }

        if ($kpValue < 6.0) {
            return 'G1';
        }

        if ($kpValue < 7.0) {
            return 'G2';
        }

        if ($kpValue < 8.0) {
            return 'G3';
        }

        if ($kpValue < 9.0) {
            return 'G4';
        }

        return 'G5';
    }

    private function auroraWatchLabel(int $score): string
    {
        if ($score >= 70) {
            return 'Vysoka sanca';
        }

        if ($score >= 40) {
            return 'Zvysena sanca';
        }

        if ($score >= 15) {
            return 'Slaba sanca';
        }

        return 'Velmi nizka sanca';
    }

    private function timeoutSeconds(): int
    {
        return max(1, (int) config('observing.sky.space_weather_timeout_seconds', 8));
    }

    /**
     * @return array<string,mixed>
     */
    private function basePayload(bool $available): array
    {
        return [
            'available' => $available,
            'source' => [
                'provider' => 'noaa_swpc',
                'label' => 'NOAA SWPC',
                'url' => 'https://www.swpc.noaa.gov/products/planetary-k-index',
            ],
            'sources' => [
                'kp' => 'https://services.swpc.noaa.gov/json/planetary_k_index_1m.json',
                'aurora' => 'https://services.swpc.noaa.gov/json/ovation_aurora_latest.json',
            ],
        ];
    }

    private function latestTimestamp(?string ...$values): ?string
    {
        $latest = null;

        foreach ($values as $value) {
            if (!is_string($value) || trim($value) === '') {
                continue;
            }

            try {
                $parsed = CarbonImmutable::parse($value);
            } catch (\Throwable) {
                continue;
            }

            if ($latest === null || $parsed->greaterThan($latest)) {
                $latest = $parsed;
            }
        }

        return $latest?->toIso8601String();
    }

    private function normalizeLongitude(float $value): float
    {
        $normalized = fmod($value + 180.0, 360.0);
        if ($normalized < 0) {
            $normalized += 360.0;
        }

        return $normalized - 180.0;
    }

    private function longitudeDistance(float $left, float $right): float
    {
        $diff = abs($this->normalizeLongitude($left) - $this->normalizeLongitude($right));
        return min($diff, 360.0 - $diff);
    }

    private function toNullableFloat(mixed $value): ?float
    {
        return is_numeric($value) ? round((float) $value, 2) : null;
    }

    private function toIso8601(mixed $value, string $tz, bool $assumeUtc = false): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        try {
            $moment = $assumeUtc && !preg_match('/(?:Z|[+\-]\d{2}:\d{2})$/', $trimmed)
                ? CarbonImmutable::parse($trimmed, 'UTC')
                : CarbonImmutable::parse($trimmed);

            return $moment->setTimezone($tz)->toIso8601String();
        } catch (\Throwable) {
            return null;
        }
    }
}
