<?php

namespace App\Services\Sky;

use App\Services\Observing\Support\ObservingHttp;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use DateTimeZone;

class SkyMoonOverviewService
{
    private const AU_IN_KM = 149597870.7;

    public function __construct(
        private readonly ObservingHttp $http,
        private readonly SkyMoonPhasesService $skyMoonPhasesService
    ) {
    }

    /**
     * @return array<string,mixed>
     */
    public function fetch(float $lat, float $lon, string $tz, ?string $referenceDate = null): array
    {
        $resolvedTz = $this->sanitizeTimezone($tz);
        $referenceAt = $this->resolveReferenceMoment($referenceDate, $resolvedTz);
        $referenceDateValue = $referenceAt->format('Y-m-d');

        $sunMoonPayload = $this->fetchUsnoOneDayPayload($lat, $lon, $referenceDateValue, $resolvedTz);
        $moonData = is_array(data_get($sunMoonPayload, 'properties.data', []))
            ? data_get($sunMoonPayload, 'properties.data', [])
            : [];

        $moonPhase = $this->normalizeMoonPhase((string) ($moonData['curphase'] ?? ''));
        $illumination = $this->parseIlluminationPercent($moonData['fracillum'] ?? null);

        $position = $this->fetchCurrentMoonPosition($lat, $lon, $referenceAt, $resolvedTz);
        $nextMoonriseAt = $this->resolveNextMoonriseAt($lat, $lon, $referenceAt, $resolvedTz);

        $phasesPayload = $this->skyMoonPhasesService->fetch($lat, $lon, $resolvedTz, $referenceDateValue);
        $nextNewMoonAt = $this->resolveNextMajorPhaseAt($phasesPayload, 'new_moon', $referenceAt);
        $nextFullMoonAt = $this->resolveNextMajorPhaseAt($phasesPayload, 'full_moon', $referenceAt);

        return [
            'reference_at' => $referenceAt->toIso8601String(),
            'timezone' => $resolvedTz,
            'moon_phase' => $moonPhase,
            'moon_illumination_percent' => $illumination,
            'moon_altitude_deg' => $position['altitude_deg'],
            'moon_azimuth_deg' => $position['azimuth_deg'],
            'moon_direction' => $position['direction'],
            'moon_distance_km' => $position['distance_km'],
            'next_new_moon_at' => $nextNewMoonAt,
            'next_full_moon_at' => $nextFullMoonAt,
            'next_moonrise_at' => $nextMoonriseAt,
            'source' => [
                'phase' => [
                    'provider' => 'USNO',
                    'label' => 'USNO Oneday API (free, bez API kluca)',
                    'url' => $this->resolveUsnoOneDayEndpointUrl(),
                    'api_key_required' => false,
                ],
                'position' => [
                    'provider' => 'JPL',
                    'label' => 'JPL Horizons API',
                    'url' => $this->resolveJplHorizonsEndpointUrl(),
                    'api_key_required' => false,
                ],
                'next_phases' => [
                    'provider' => 'USNO',
                    'label' => 'USNO Moon Phases API (free, bez API kluca)',
                    'url' => $this->resolveMoonPhasesYearEndpointUrl(),
                    'api_key_required' => false,
                ],
            ],
        ];
    }

    /**
     * @return array{altitude_deg:?float,azimuth_deg:?float,direction:?string,distance_km:?int}
     */
    private function fetchCurrentMoonPosition(float $lat, float $lon, CarbonImmutable $referenceAt, string $tz): array
    {
        $providerUrl = $this->resolveJplHorizonsEndpointUrl();
        if ($providerUrl === '') {
            return [
                'altitude_deg' => null,
                'azimuth_deg' => null,
                'direction' => null,
                'distance_km' => null,
            ];
        }

        $siteCoord = sprintf('%0.6f,%0.6f,0', $lon, $lat);
        $timeLabel = $referenceAt->setTimezone($tz)->format('Y-m-d H:i');

        try {
            $payload = $this->http->getJson(
                'jpl_horizons_moon_position',
                $providerUrl,
                [
                    'format' => 'json',
                    'COMMAND' => "'301'",
                    'MAKE_EPHEM' => "'YES'",
                    'EPHEM_TYPE' => "'OBSERVER'",
                    'CENTER' => "'coord@399'",
                    'COORD_TYPE' => "'GEODETIC'",
                    'SITE_COORD' => "'".$siteCoord."'",
                    'TLIST' => "'".$timeLabel."'",
                    'QUANTITIES' => "'1,4,9,20,23'",
                ]
            );
        } catch (\Throwable) {
            return [
                'altitude_deg' => null,
                'azimuth_deg' => null,
                'direction' => null,
                'distance_km' => null,
            ];
        }

        $resultBody = is_array($payload) ? (string) ($payload['result'] ?? '') : '';
        if ($resultBody === '') {
            return [
                'altitude_deg' => null,
                'azimuth_deg' => null,
                'direction' => null,
                'distance_km' => null,
            ];
        }

        $parsed = $this->parseHorizonsBody($resultBody);
        if ($parsed === null) {
            return [
                'altitude_deg' => null,
                'azimuth_deg' => null,
                'direction' => null,
                'distance_km' => null,
            ];
        }

        $azimuth = $this->toFloat($parsed['azimuth'] ?? null);
        $altitude = $this->toFloat($parsed['altitude'] ?? null);
        $distanceAu = $this->toFloat($parsed['distance_au'] ?? null);

        return [
            'altitude_deg' => $altitude !== null ? round(max(-90.0, min(90.0, $altitude)), 2) : null,
            'azimuth_deg' => $azimuth !== null ? round(fmod($azimuth + 360.0, 360.0), 2) : null,
            'direction' => $azimuth !== null ? $this->azimuthToDirection($azimuth) : null,
            'distance_km' => $distanceAu !== null ? (int) round($distanceAu * self::AU_IN_KM) : null,
        ];
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
            'distance_au' => (string) ($tokens[12] ?? ''),
        ];
    }

    private function azimuthToDirection(float $azimuth): string
    {
        $directions = ['N', 'NE', 'E', 'SE', 'S', 'SW', 'W', 'NW'];
        $index = (int) floor(((fmod($azimuth + 360.0, 360.0) + 22.5) / 45.0)) % 8;

        return $directions[$index];
    }

    private function parseIlluminationPercent(mixed $value): ?int
    {
        if (is_numeric($value)) {
            $numeric = (float) $value;
            if ($numeric > 1.0) {
                return (int) round(max(0.0, min(100.0, $numeric)));
            }

            return (int) round(max(0.0, min(1.0, $numeric)) * 100);
        }

        if (!is_string($value)) {
            return null;
        }

        $normalized = trim(str_replace('%', '', $value));
        if ($normalized === '' || !is_numeric($normalized)) {
            return null;
        }

        return (int) round(max(0.0, min(100.0, (float) $normalized)));
    }

    private function normalizeMoonPhase(string $raw): string
    {
        $normalized = strtolower(trim($raw));

        return match ($normalized) {
            'new moon' => 'new_moon',
            'waxing crescent' => 'waxing_crescent',
            'first quarter' => 'first_quarter',
            'waxing gibbous' => 'waxing_gibbous',
            'full moon' => 'full_moon',
            'waning gibbous' => 'waning_gibbous',
            'last quarter', 'third quarter' => 'last_quarter',
            'waning crescent' => 'waning_crescent',
            default => 'unknown',
        };
    }

    private function resolveNextMajorPhaseAt(array $payload, string $phaseKey, CarbonImmutable $referenceAt): ?string
    {
        $events = is_array($payload['major_events'] ?? null) ? $payload['major_events'] : [];
        foreach ($events as $event) {
            if (!is_array($event)) {
                continue;
            }

            if (trim((string) ($event['key'] ?? '')) !== $phaseKey) {
                continue;
            }

            $at = trim((string) ($event['at'] ?? ''));
            if ($at === '') {
                continue;
            }

            try {
                $atMoment = CarbonImmutable::parse($at, $referenceAt->getTimezone());
            } catch (\Throwable) {
                continue;
            }

            if ($atMoment->greaterThanOrEqualTo($referenceAt)) {
                return $atMoment->toIso8601String();
            }
        }

        return null;
    }

    private function resolveNextMoonriseAt(float $lat, float $lon, CarbonImmutable $referenceAt, string $tz): ?string
    {
        for ($offset = 0; $offset <= 2; $offset++) {
            $date = $referenceAt->addDays($offset)->format('Y-m-d');
            $payload = $this->fetchUsnoOneDayPayload($lat, $lon, $date, $tz);
            $moonData = data_get($payload, 'properties.data.moondata');
            if (!is_array($moonData)) {
                continue;
            }

            $riseLocal = $this->findPhenTime($moonData, 'rise');
            if (!is_string($riseLocal)) {
                continue;
            }

            try {
                $riseAt = CarbonImmutable::createFromFormat('Y-m-d H:i', "{$date} {$riseLocal}", $tz);
            } catch (\Throwable) {
                continue;
            }

            if ($riseAt->greaterThan($referenceAt)) {
                return $riseAt->toIso8601String();
            }
        }

        return null;
    }

    private function findPhenTime(array $rows, string $needle): ?string
    {
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $phen = strtolower((string) ($row['phen'] ?? ''));
            if ($phen !== '' && str_contains($phen, $needle)) {
                $time = trim((string) ($row['time'] ?? ''));
                if (preg_match('/^\d{2}:\d{2}$/', $time) === 1) {
                    return $time;
                }
            }
        }

        return null;
    }

    /**
     * @return array<string,mixed>
     */
    private function fetchUsnoOneDayPayload(float $lat, float $lon, string $date, string $tz): array
    {
        $timeConfig = $this->resolveUsnoTimezone($tz, $date);

        return $this->http->getJson(
            'usno_moon_overview',
            $this->resolveUsnoOneDayEndpointUrl(),
            [
                'date' => $date,
                'coords' => number_format($lat, 6, '.', '').','.number_format($lon, 6, '.', ''),
                'tz' => (int) $timeConfig['tz'],
                'dst' => $timeConfig['dst'] ? 'true' : 'false',
            ]
        );
    }

    /**
     * @return array{tz:int,dst:bool}
     */
    private function resolveUsnoTimezone(string $ianaTimezone, string $date): array
    {
        $fallbackTz = (string) config('observing.default_timezone', 'Europe/Bratislava');
        $tzName = trim($ianaTimezone) !== '' ? trim($ianaTimezone) : $fallbackTz;

        try {
            $zone = new DateTimeZone($tzName);
        } catch (\Throwable) {
            try {
                $zone = new DateTimeZone($fallbackTz);
            } catch (\Throwable) {
                return [
                    'tz' => 0,
                    'dst' => false,
                ];
            }
        }

        $dt = new DateTimeImmutable("{$date} 12:00:00", $zone);
        $isDst = $dt->format('I') === '1';
        $offsetHours = (int) round($dt->getOffset() / 3600);
        $baseOffset = $isDst ? $offsetHours - 1 : $offsetHours;

        return [
            'tz' => (int) $baseOffset,
            'dst' => $isDst,
        ];
    }

    private function resolveReferenceMoment(?string $referenceDate, string $tz): CarbonImmutable
    {
        if (is_string($referenceDate) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $referenceDate) === 1) {
            try {
                return CarbonImmutable::createFromFormat('Y-m-d', $referenceDate, $tz)->startOfDay();
            } catch (\Throwable) {
                // Fallback below.
            }
        }

        return CarbonImmutable::now($tz);
    }

    private function sanitizeTimezone(string $value): string
    {
        $default = (string) config('observing.default_timezone', 'Europe/Bratislava');
        $candidate = trim($value);

        if ($candidate === '') {
            return $default;
        }

        return in_array($candidate, timezone_identifiers_list(), true) ? $candidate : $default;
    }

    private function resolveUsnoOneDayEndpointUrl(): string
    {
        $configured = trim((string) config('observing.providers.usno_url', 'https://aa.usno.navy.mil/api/rstt/oneday'));

        return $configured !== '' ? $configured : 'https://aa.usno.navy.mil/api/rstt/oneday';
    }

    private function resolveMoonPhasesYearEndpointUrl(): string
    {
        $configured = trim((string) config(
            'events.nasa_watch_the_skies.moon_phases_year_url',
            config('events.nasa_watch_the_skies.url', 'https://aa.usno.navy.mil/api/moon/phases/year')
        ));

        return $configured !== '' ? $configured : 'https://aa.usno.navy.mil/api/moon/phases/year';
    }

    private function resolveJplHorizonsEndpointUrl(): string
    {
        $configured = trim((string) config('observing.providers.jpl_horizons_url', 'https://ssd.jpl.nasa.gov/api/horizons.api'));

        return $configured !== '' ? $configured : 'https://ssd.jpl.nasa.gov/api/horizons.api';
    }

    private function toFloat(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }
}

