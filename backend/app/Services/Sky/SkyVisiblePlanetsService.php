<?php

namespace App\Services\Sky;

use App\Services\Observing\SkyMicroserviceClient;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;

class SkyVisiblePlanetsService
{
    public function __construct(
        private readonly SkyMicroserviceClient $skyMicroserviceClient
    ) {
    }

    /**
     * @return array{planets:array<int,array<string,mixed>>,reason?:string}
     */
    public function fetch(float $lat, float $lon, string $tz): array
    {
        $localDate = CarbonImmutable::now($tz)->format('Y-m-d');

        try {
            $payload = $this->skyMicroserviceClient->fetch($lat, $lon, $localDate, $tz);
        } catch (\Throwable $exception) {
            Log::warning('Sky visible planets microservice failed.', [
                'lat' => $lat,
                'lon' => $lon,
                'tz' => $tz,
                'date' => $localDate,
                'microservice_base' => config('observing.sky_summary.microservice_base'),
                'exception_class' => $exception::class,
                'exception_message' => $exception->getMessage(),
            ]);

            return [
                'planets' => [],
                'reason' => 'sky_service_unavailable',
            ];
        }

        $planets = is_array($payload['planets'] ?? null) ? $payload['planets'] : [];
        $normalized = [];

        foreach ($planets as $planet) {
            if (!is_array($planet)) {
                continue;
            }

            $altitude = $this->toFloat($planet['alt_max_deg'] ?? null);
            $azimuth = $this->toFloat($planet['az_at_best_deg'] ?? null);
            $sunAltitude = $this->toFloat($planet['sun_altitude_deg'] ?? null);

            if ($altitude === null || $azimuth === null || $altitude < 5.0) {
                continue;
            }

            if ($sunAltitude !== null && $sunAltitude >= -6.0) {
                continue;
            }

            $name = trim((string) ($planet['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $direction = $this->normalizeDirection($planet['direction'] ?? null, $azimuth);
            $bestFrom = $this->toClock($planet['best_from'] ?? null);
            $bestTo = $this->toClock($planet['best_to'] ?? null);
            $magnitude = $this->toFloat($planet['magnitude'] ?? null);

            $item = [
                'name' => $name,
                'altitude_deg' => round($altitude, 1),
                'azimuth_deg' => round($azimuth, 1),
                'direction' => $direction,
                'quality' => $this->qualityForAltitude($altitude),
            ];

            if ($magnitude !== null) {
                $item['magnitude'] = round($magnitude, 1);
            }

            if ($bestFrom !== null && $bestTo !== null) {
                $item['best_time_window'] = "{$bestFrom}-{$bestTo}";
            }

            $normalized[] = $item;
        }

        usort($normalized, static function (array $a, array $b): int {
            return ($b['altitude_deg'] <=> $a['altitude_deg']) ?: strcmp((string) $a['name'], (string) $b['name']);
        });

        return [
            'planets' => array_values($normalized),
        ];
    }

    private function toFloat(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }

    private function toClock(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $trimmed = trim($value);
        return preg_match('/^\d{2}:\d{2}$/', $trimmed) ? $trimmed : null;
    }

    private function normalizeDirection(mixed $value, float $azimuth): string
    {
        $candidate = strtoupper(trim((string) $value));
        if (in_array($candidate, ['N', 'NE', 'E', 'SE', 'S', 'SW', 'W', 'NW'], true)) {
            return $candidate;
        }

        $directions = ['N', 'NE', 'E', 'SE', 'S', 'SW', 'W', 'NW'];
        $index = (int) floor(((fmod($azimuth + 360.0, 360.0) + 22.5) / 45.0)) % 8;

        return $directions[$index];
    }

    private function qualityForAltitude(float $altitude): string
    {
        if ($altitude >= 30.0) {
            return 'excellent';
        }

        if ($altitude >= 15.0) {
            return 'good';
        }

        return 'low';
    }
}
