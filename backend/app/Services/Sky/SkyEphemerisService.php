<?php

namespace App\Services\Sky;

use App\Support\Http\SslVerificationPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Log;

class SkyEphemerisService
{
    private const PLANET_TARGETS = [
        ['name' => 'Merkur', 'command' => '199'],
        ['name' => 'Venusa', 'command' => '299'],
        ['name' => 'Mars', 'command' => '499'],
        ['name' => 'Jupiter', 'command' => '599'],
        ['name' => 'Saturn', 'command' => '699'],
    ];

    public function __construct(
        private readonly HttpFactory $http,
        private readonly SslVerificationPolicy $sslVerificationPolicy,
    ) {
    }

    /**
     * @return array{
     *   sample_at:string,
     *   planets:array<int,array<string,mixed>>,
     *   sun_altitude_deg:?float,
     *   source:string
     * }
     */
    public function fetchPlanets(float $lat, float $lon, string $tz): array
    {
        $sampleAt = CarbonImmutable::now($tz)->toIso8601String();
        $sampleMoment = CarbonImmutable::parse($sampleAt, $tz);
        $providerUrl = trim((string) config('observing.providers.jpl_horizons_url', ''));
        $siteCoord = sprintf('%0.6f,%0.6f,0', $lon, $lat);
        $timeLabel = $sampleMoment->format('Y-m-d H:i');

        return [
            'sample_at' => $sampleAt,
            'planets' => $this->fetchPlanetEphemerides($providerUrl, $siteCoord, $timeLabel, $sampleAt),
            'sun_altitude_deg' => $this->fetchSunAltitude($providerUrl, $siteCoord, $timeLabel),
            'source' => 'jpl_horizons',
        ];
    }

    /**
     * @return array{
     *   sample_at:string,
     *   planets:array<int,array<string,mixed>>,
     *   sun_altitude_deg:?float,
     *   comets:array<int,array<string,mixed>>,
     *   asteroids:array<int,array<string,mixed>>,
     *   source:array{
     *     planets:string,
     *     small_bodies:string
     *   }
     * }
     */
    public function fetch(float $lat, float $lon, string $tz): array
    {
        $planetsPayload = $this->fetchPlanets($lat, $lon, $tz);

        return [
            'sample_at' => $planetsPayload['sample_at'],
            'planets' => $planetsPayload['planets'],
            'sun_altitude_deg' => $planetsPayload['sun_altitude_deg'],
            'comets' => $this->fetchSmallBodies('c'),
            'asteroids' => $this->fetchSmallBodies('a'),
            'source' => [
                'planets' => 'jpl_horizons',
                'small_bodies' => 'jpl_sbddb',
            ],
        ];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function fetchNeoWatchlist(int $limit = 5): array
    {
        $providerUrl = trim((string) config('observing.providers.jpl_sbdd_url', ''));
        if ($providerUrl === '') {
            return [];
        }

        try {
            $response = $this->jsonRequest()
                ->get($providerUrl, [
                    'fields' => 'full_name,pdes,class,neo,pha,moid,diameter,H',
                    'sb-group' => 'neo',
                    'sort' => '-pha,moid',
                    'limit' => max(1, $limit),
                ]);
        } catch (\Throwable $exception) {
            $this->logProviderFailure('jpl_sbddb', $providerUrl, $exception);
            return [];
        }

        if (!$response->successful()) {
            return [];
        }

        $payload = $response->json();
        $fields = is_array($payload['fields'] ?? null) ? $payload['fields'] : [];
        $dataRows = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        if ($fields === [] || $dataRows === []) {
            return [];
        }

        $normalized = [];

        foreach ($dataRows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $assoc = [];
            foreach ($fields as $index => $fieldName) {
                if (!is_string($fieldName) || $fieldName === '') {
                    continue;
                }

                $assoc[$fieldName] = $row[$index] ?? null;
            }

            $designation = $this->sanitizeText($assoc['pdes'] ?? null);
            $name = $this->normalizeWatchlistName(
                $this->sanitizeText($assoc['full_name'] ?? null),
                $designation
            );
            if ($name === '') {
                continue;
            }

            $orbitClassCode = $this->normalizeOrbitClassCode($assoc['class'] ?? null);
            $normalized[] = [
                'name' => $name,
                'designation' => $designation,
                'orbit_class_code' => $orbitClassCode,
                'orbit_class_label' => $this->orbitClassLabel($orbitClassCode),
                'neo' => $this->normalizeBooleanLike($assoc['neo'] ?? null),
                'pha' => $this->normalizeBooleanLike($assoc['pha'] ?? null),
                'moid_au' => $this->toRoundedFloat($assoc['moid'] ?? null, 6),
                'diameter_km' => $this->toRoundedFloat($assoc['diameter'] ?? null, 3),
                'absolute_magnitude' => $this->toRoundedFloat($assoc['H'] ?? null, 2),
            ];
        }

        usort($normalized, static function (array $left, array $right): int {
            $leftPha = $left['pha'] === true ? 1 : 0;
            $rightPha = $right['pha'] === true ? 1 : 0;
            if ($leftPha !== $rightPha) {
                return $rightPha <=> $leftPha;
            }

            $leftMoid = is_numeric($left['moid_au'] ?? null) ? (float) $left['moid_au'] : INF;
            $rightMoid = is_numeric($right['moid_au'] ?? null) ? (float) $right['moid_au'] : INF;
            if ($leftMoid !== $rightMoid) {
                return $leftMoid <=> $rightMoid;
            }

            return strcasecmp((string) ($left['name'] ?? ''), (string) ($right['name'] ?? ''));
        });

        return array_values(array_slice($normalized, 0, max(1, $limit)));
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function fetchPlanetEphemerides(string $providerUrl, string $siteCoord, string $timeLabel, string $sampleAt): array
    {
        if ($providerUrl === '') {
            return [];
        }
        $rows = [];

        foreach (self::PLANET_TARGETS as $target) {
            try {
                $response = $this->jsonRequest()
                    ->get($providerUrl, [
                        'format' => 'json',
                        'COMMAND' => "'" . $target['command'] . "'",
                        'MAKE_EPHEM' => "'YES'",
                        'EPHEM_TYPE' => "'OBSERVER'",
                        'CENTER' => "'coord@399'",
                        'COORD_TYPE' => "'GEODETIC'",
                        'SITE_COORD' => "'" . $siteCoord . "'",
                        'TLIST' => "'" . $timeLabel . "'",
                        'QUANTITIES' => "'1,4,9,20,23'",
                    ]);
            } catch (\Throwable $exception) {
                $this->logProviderFailure('jpl_horizons', $providerUrl, $exception, [
                    'target' => $target['command'],
                ]);
                continue;
            }

            if (!$response->successful()) {
                continue;
            }

            $payload = $response->json();
            $resultBody = is_array($payload) ? (string) ($payload['result'] ?? '') : '';
            if ($resultBody === '') {
                continue;
            }

            $parsed = $this->parseHorizonsBody($resultBody);
            if ($parsed === null) {
                continue;
            }

            $azimuth = $this->toFloat($parsed['azimuth'] ?? null);
            $altitude = $this->toFloat($parsed['altitude'] ?? null);
            $magnitude = $this->toFloat($parsed['magnitude'] ?? null);
            $elongation = $this->toFloat($parsed['elongation'] ?? null);
            $distance = $this->toFloat($parsed['distance_au'] ?? null);
            $radialVelocity = $this->toFloat($parsed['radial_velocity_kms'] ?? null);

            if ($azimuth === null || $altitude === null) {
                continue;
            }

            $row = [
                'name' => $target['name'],
                'azimuth_deg' => round($azimuth, 4),
                'altitude_deg' => round($altitude, 4),
                'direction' => $this->azimuthToDirection($azimuth),
                'quality' => $this->qualityForAltitude($altitude),
                'sample_at' => $sampleAt,
            ];

            if ($magnitude !== null) {
                $row['magnitude'] = round($magnitude, 3);
            }
            if ($elongation !== null) {
                $row['elongation_deg'] = round($elongation, 4);
            }
            if ($distance !== null) {
                $row['distance_au'] = round($distance, 8);
            }
            if ($radialVelocity !== null) {
                $row['radial_velocity_kms'] = round($radialVelocity, 6);
            }

            $rows[] = $row;
        }

        usort($rows, static fn (array $a, array $b): int => ($b['altitude_deg'] <=> $a['altitude_deg']));

        return array_values($rows);
    }

    private function fetchSunAltitude(string $providerUrl, string $siteCoord, string $timeLabel): ?float
    {
        if ($providerUrl === '') {
            return null;
        }

        try {
            $response = $this->jsonRequest()
                ->get($providerUrl, [
                    'format' => 'json',
                    'COMMAND' => "'10'",
                    'MAKE_EPHEM' => "'YES'",
                    'EPHEM_TYPE' => "'OBSERVER'",
                    'CENTER' => "'coord@399'",
                    'COORD_TYPE' => "'GEODETIC'",
                    'SITE_COORD' => "'" . $siteCoord . "'",
                    'TLIST' => "'" . $timeLabel . "'",
                    'QUANTITIES' => "'1,4,9,20,23'",
                ]);
        } catch (\Throwable $exception) {
            $this->logProviderFailure('jpl_horizons', $providerUrl, $exception, [
                'target' => '10',
            ]);
            return null;
        }

        if (!$response->successful()) {
            return null;
        }

        $payload = $response->json();
        $resultBody = is_array($payload) ? (string) ($payload['result'] ?? '') : '';
        if ($resultBody === '') {
            return null;
        }

        $parsed = $this->parseHorizonsBody($resultBody);
        if ($parsed === null) {
            return null;
        }

        $altitude = $this->toFloat($parsed['altitude'] ?? null);
        if ($altitude === null) {
            return null;
        }

        return round($altitude, 4);
    }

    /**
     * @return array<string,string>|null
     */
    private function parseHorizonsBody(string $body): ?array
    {
        $start = strpos($body, '$$SOE');
        $end = strpos($body, '$$EOE');
        if ($start === false || $end === false || $end <= $start) {
            return null;
        }

        $chunk = substr($body, $start + 5, $end - ($start + 5));
        $lines = preg_split('/\R+/', (string) $chunk) ?: [];
        $line = '';

        foreach ($lines as $candidate) {
            $trimmed = trim((string) $candidate);
            if ($trimmed !== '') {
                $line = $trimmed;
                break;
            }
        }

        if ($line === '') {
            return null;
        }

        $tokens = preg_split('/\s+/', $line) ?: [];
        if (count($tokens) < 15) {
            return null;
        }

        return [
            'azimuth' => (string) ($tokens[8] ?? ''),
            'altitude' => (string) ($tokens[9] ?? ''),
            'magnitude' => (string) ($tokens[10] ?? ''),
            'distance_au' => (string) ($tokens[12] ?? ''),
            'radial_velocity_kms' => (string) ($tokens[13] ?? ''),
            'elongation' => (string) ($tokens[14] ?? ''),
        ];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function fetchSmallBodies(string $kind): array
    {
        $providerUrl = trim((string) config('observing.providers.jpl_sbdd_url', ''));
        if ($providerUrl === '') {
            return [];
        }

        try {
            $response = $this->jsonRequest()
                ->get($providerUrl, [
                    'fields' => 'full_name,pdes,kind,neo,pha,e,a,q,i,om,w,tp,moid',
                    'limit' => 5,
                    'sb-kind' => $kind,
                ]);
        } catch (\Throwable $exception) {
            $this->logProviderFailure('jpl_sbddb', $providerUrl, $exception, [
                'small_body_kind' => $kind,
            ]);
            return [];
        }

        if (!$response->successful()) {
            return [];
        }

        $payload = $response->json();
        $fields = is_array($payload['fields'] ?? null) ? $payload['fields'] : [];
        $dataRows = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        if ($fields === [] || $dataRows === []) {
            return [];
        }

        $normalized = [];

        foreach ($dataRows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $assoc = [];
            foreach ($fields as $index => $fieldName) {
                if (!is_string($fieldName) || $fieldName === '') {
                    continue;
                }

                $assoc[$fieldName] = $row[$index] ?? null;
            }

            $name = trim((string) ($assoc['full_name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $normalized[] = [
                'name' => $name,
                'designation' => $this->sanitizeText($assoc['pdes'] ?? null),
                'orbit_kind' => $this->sanitizeText($assoc['kind'] ?? null),
                'neo' => $this->normalizeBooleanLike($assoc['neo'] ?? null),
                'pha' => $this->normalizeBooleanLike($assoc['pha'] ?? null),
                'eccentricity' => $this->toRoundedFloat($assoc['e'] ?? null, 6),
                'semi_major_axis_au' => $this->toRoundedFloat($assoc['a'] ?? null, 6),
                'perihelion_au' => $this->toRoundedFloat($assoc['q'] ?? null, 6),
                'inclination_deg' => $this->toRoundedFloat($assoc['i'] ?? null, 4),
                'ascending_node_deg' => $this->toRoundedFloat($assoc['om'] ?? null, 4),
                'arg_perihelion_deg' => $this->toRoundedFloat($assoc['w'] ?? null, 4),
                'perihelion_jd' => $this->toRoundedFloat($assoc['tp'] ?? null, 6),
                'moid_au' => $this->toRoundedFloat($assoc['moid'] ?? null, 6),
            ];
        }

        return array_values($normalized);
    }

    private function azimuthToDirection(float $azimuth): string
    {
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

    private function sanitizeText(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $trimmed = trim($value);
        return $trimmed !== '' ? $trimmed : null;
    }

    private function normalizeWatchlistName(?string $fullName, ?string $designation): string
    {
        $candidate = trim((string) $fullName);
        if ($candidate === '') {
            return trim((string) $designation);
        }

        if (preg_match('/^\(([^)]+)\)$/', $candidate) === 1) {
            return trim((string) ($designation ?: trim($candidate, '()')));
        }

        return $candidate;
    }

    private function normalizeOrbitClassCode(mixed $value): ?string
    {
        $candidate = strtoupper(trim((string) $value));
        return $candidate !== '' ? $candidate : null;
    }

    private function orbitClassLabel(?string $code): ?string
    {
        return match (strtoupper(trim((string) $code))) {
            'IEO' => 'Atira',
            'ATE' => 'Aten',
            'APO' => 'Apollo',
            'AMO' => 'Amor',
            'VAT' => 'Vatira',
            default => $code,
        };
    }

    private function normalizeBooleanLike(mixed $value): ?bool
    {
        $candidate = strtoupper(trim((string) $value));
        if ($candidate === 'Y') {
            return true;
        }
        if ($candidate === 'N') {
            return false;
        }

        return null;
    }

    private function toRoundedFloat(mixed $value, int $precision): ?float
    {
        $numeric = $this->toFloat($value);
        if ($numeric === null) {
            return null;
        }

        return round($numeric, $precision);
    }

    private function toFloat(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }

    private function jsonRequest(): PendingRequest
    {
        $request = $this->http
            ->timeout((int) config('observing.http.timeout_seconds', 8))
            ->retry(
                (int) config('observing.http.retry_times', 2),
                (int) config('observing.http.retry_sleep_ms', 200)
            )
            ->acceptJson();

        $verifyOption = $this->resolveSslVerifyOption();

        return $request
            ->withOptions(['verify' => $verifyOption])
            ->withAttributes(['ssl_verify' => $verifyOption]);
    }

    private function resolveSslVerifyOption(): bool|string
    {
        $caBundlePath = trim((string) config('observing.http.local_ca_bundle_path', ''));
        if (app()->environment('local') && $caBundlePath !== '' && is_file($caBundlePath)) {
            return $caBundlePath;
        }

        return $this->sslVerificationPolicy->resolveVerifyOption();
    }

    /**
     * @param  array<string,mixed>  $context
     */
    private function logProviderFailure(string $provider, string $url, \Throwable $exception, array $context = []): void
    {
        Log::warning('Sky ephemeris provider request failed.', [
            'provider' => $provider,
            'url' => $url,
            'exception_class' => $exception::class,
            'exception_message' => $exception->getMessage(),
            ...$context,
        ]);
    }
}
