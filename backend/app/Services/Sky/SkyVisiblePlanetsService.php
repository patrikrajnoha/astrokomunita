<?php

namespace App\Services\Sky;

use App\Services\Observing\SkyMicroserviceClient;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SkyVisiblePlanetsService
{
    public function __construct(
        private readonly SkyMicroserviceClient $skyMicroserviceClient,
        private readonly SkyEphemerisService $skyEphemerisService
    ) {
    }

    /**
     * @return array{
     *   planets:array<int,array<string,mixed>>,
     *   sample_at?:?string,
     *   sun_altitude_deg?:?float,
     *   reason?:string
     * }
     */
    public function fetch(float $lat, float $lon, string $tz): array
    {
        $localDate = CarbonImmutable::now($tz)->format('Y-m-d');

        try {
            $jplPayload = $this->skyEphemerisService->fetchPlanets($lat, $lon, $tz);
            $normalizedJpl = $this->normalizeJplPayload($jplPayload);
            if ($normalizedJpl !== null) {
                return $this->enrichJplPayloadWithBestWindows(
                    payload: $normalizedJpl,
                    lat: $lat,
                    lon: $lon,
                    localDate: $localDate,
                    tz: $tz
                );
            }
        } catch (\Throwable $exception) {
            Log::warning('Sky visible planets JPL Horizons provider failed.', [
                'lat' => $lat,
                'lon' => $lon,
                'tz' => $tz,
                'date' => $localDate,
                'provider_url' => config('observing.providers.jpl_horizons_url'),
                'exception_class' => $exception::class,
                'exception_message' => $exception->getMessage(),
            ]);
        }

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
                'sample_at' => null,
                'sun_altitude_deg' => null,
                'reason' => 'sky_service_unavailable',
            ];
        }

        $sampleAt = $this->toIso8601($payload['sample_at'] ?? null);
        $sunAltitude = $this->toFloat($payload['sun_altitude_deg'] ?? null);
        $hasPlanetsArray = array_key_exists('planets', $payload) && is_array($payload['planets']);
        $planets = $hasPlanetsArray ? $payload['planets'] : [];

        if ($sampleAt === null || $sunAltitude === null || !$hasPlanetsArray || !$this->hasRequiredPlanetContract($planets)) {
            return [
                'planets' => [],
                'sample_at' => $sampleAt,
                'sun_altitude_deg' => $sunAltitude,
                'reason' => 'degraded_contract',
            ];
        }

        $normalized = [];

        foreach ($planets as $planet) {
            if (!is_array($planet)) {
                continue;
            }

            $altitude = $this->toFloat($planet['alt_max_deg'] ?? null);
            $azimuth = $this->toFloat($planet['az_at_best_deg'] ?? null);
            $elongation = $this->toFloat($planet['elongation_deg'] ?? null);

            if ($altitude === null || $azimuth === null || $altitude < 5.0) {
                continue;
            }

            $name = trim((string) ($planet['name'] ?? ''));
            if ($name === '' || $elongation === null) {
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
                'elongation_deg' => round($elongation, 1),
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
            'sample_at' => $sampleAt,
            'sun_altitude_deg' => round($sunAltitude, 1),
            'source' => 'sky_microservice',
        ];
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>|null
     */
    private function normalizeJplPayload(array $payload): ?array
    {
        $sampleAt = $this->toIso8601($payload['sample_at'] ?? null);
        $sunAltitude = $this->toFloat($payload['sun_altitude_deg'] ?? null);
        $planets = is_array($payload['planets'] ?? null) ? $payload['planets'] : null;

        if ($sampleAt === null || $sunAltitude === null || $planets === null || $planets === []) {
            return null;
        }

        $normalized = [];

        foreach ($planets as $planet) {
            if (!is_array($planet)) {
                continue;
            }

            $name = trim((string) ($planet['name'] ?? ''));
            $altitude = $this->toFloat($planet['altitude_deg'] ?? null);
            $azimuth = $this->toFloat($planet['azimuth_deg'] ?? null);
            $elongation = $this->toFloat($planet['elongation_deg'] ?? null);
            $quality = trim((string) ($planet['quality'] ?? ''));
            $direction = trim((string) ($planet['direction'] ?? ''));
            $magnitude = $this->toFloat($planet['magnitude'] ?? null);

            if ($name === '' || $altitude === null || $azimuth === null || $elongation === null) {
                continue;
            }

            if ($quality === '') {
                $quality = $this->qualityForAltitude($altitude);
            }

            if ($direction === '') {
                $direction = $this->normalizeDirection(null, $azimuth);
            }

            $item = [
                'name' => $name,
                'altitude_deg' => round($altitude, 1),
                'azimuth_deg' => round($azimuth, 1),
                'elongation_deg' => round($elongation, 1),
                'direction' => $direction,
                'quality' => $quality,
            ];

            if ($magnitude !== null) {
                $item['magnitude'] = round($magnitude, 1);
            }

            $normalized[] = $item;
        }

        if ($normalized === []) {
            return null;
        }

        usort($normalized, static function (array $a, array $b): int {
            return ($b['altitude_deg'] <=> $a['altitude_deg']) ?: strcmp((string) $a['name'], (string) $b['name']);
        });

        return [
            'planets' => array_values($normalized),
            'sample_at' => $sampleAt,
            'sun_altitude_deg' => round($sunAltitude, 1),
            'source' => 'jpl_horizons',
        ];
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    private function enrichJplPayloadWithBestWindows(
        array $payload,
        float $lat,
        float $lon,
        string $localDate,
        string $tz
    ): array {
        $planets = is_array($payload['planets'] ?? null) ? $payload['planets'] : [];
        if ($planets === []) {
            return $payload;
        }

        try {
            $microPayload = $this->skyMicroserviceClient->fetch($lat, $lon, $localDate, $tz);
        } catch (\Throwable) {
            return $payload;
        }

        $bestWindowMap = $this->extractBestWindowMap($microPayload);
        if ($bestWindowMap === []) {
            return $payload;
        }

        $enriched = [];

        foreach ($planets as $planet) {
            if (!is_array($planet)) {
                continue;
            }

            $name = trim((string) ($planet['name'] ?? ''));
            $normalizedName = $this->normalizePlanetNameKey($name);

            if (
                $normalizedName !== ''
                && !isset($planet['best_time_window'])
                && isset($bestWindowMap[$normalizedName])
            ) {
                $planet['best_time_window'] = $bestWindowMap[$normalizedName];
            }

            $enriched[] = $planet;
        }

        $payload['planets'] = array_values($enriched);

        return $payload;
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

    private function toIso8601(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($trimmed)->toIso8601String();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,string>
     */
    private function extractBestWindowMap(array $payload): array
    {
        $planets = is_array($payload['planets'] ?? null) ? $payload['planets'] : [];
        if ($planets === []) {
            return [];
        }

        $map = [];

        foreach ($planets as $planet) {
            if (!is_array($planet)) {
                continue;
            }

            $name = trim((string) ($planet['name'] ?? ''));
            $bestFrom = $this->toClock($planet['best_from'] ?? null);
            $bestTo = $this->toClock($planet['best_to'] ?? null);
            if ($name === '' || $bestFrom === null || $bestTo === null) {
                continue;
            }

            $key = $this->normalizePlanetNameKey($name);
            if ($key === '' || isset($map[$key])) {
                continue;
            }

            $map[$key] = "{$bestFrom}-{$bestTo}";
        }

        return $map;
    }

    /**
     * @param array<int,mixed> $planets
     */
    private function hasRequiredPlanetContract(array $planets): bool
    {
        foreach ($planets as $planet) {
            if (!is_array($planet)) {
                return false;
            }

            if ($this->toFloat($planet['elongation_deg'] ?? null) === null) {
                return false;
            }
        }

        return true;
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

    private function normalizePlanetNameKey(string $name): string
    {
        $normalized = Str::of($name)->ascii()->lower()->value();
        $normalized = preg_replace('/[^a-z0-9]+/', '', $normalized) ?? $normalized;
        $normalized = trim($normalized);

        return match ($normalized) {
            'merkur', 'mercury' => 'mercury',
            'venusa', 'venus' => 'venus',
            default => $normalized,
        };
    }
}
