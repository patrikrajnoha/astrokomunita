<?php

namespace App\Services\Sky;

use App\Services\Observing\Support\ObservingHttp;
use Carbon\CarbonImmutable;

class SkyMoonEventsService
{
    private const AU_IN_KM = 149597870.7;
    private const BAND_RATIO = 0.10;

    /** @var array<string,float|null> */
    private array $distanceCache = [];

    public function __construct(
        private readonly ObservingHttp $http
    ) {
    }

    /**
     * @return array<string,mixed>
     */
    public function fetch(int $year, string $tz): array
    {
        $resolvedTz = $this->sanitizeTimezone($tz);
        $phaseEvents = $this->loadPhaseEvents($year, $resolvedTz);

        $fullMoonEvents = array_values(array_filter(
            $phaseEvents,
            static fn (array $event): bool => $event['phase'] === 'full_moon'
        ));
        $newMoonEvents = array_values(array_filter(
            $phaseEvents,
            static fn (array $event): bool => $event['phase'] === 'new_moon'
        ));

        $distancesKm = $this->resolveDistancesKm(array_merge($fullMoonEvents, $newMoonEvents));

        $superFullMoonKeys = $this->resolveClosestBandKeys($fullMoonEvents, $distancesKm);
        $microFullMoonKeys = $this->resolveFarthestBandKeys($fullMoonEvents, $distancesKm);
        $superNewMoonKeys = $this->resolveClosestBandKeys($newMoonEvents, $distancesKm);
        $blueMoonKeys = $this->resolveSecondInMonthKeys($fullMoonEvents);
        $blackMoonKeys = $this->resolveSecondInMonthKeys($newMoonEvents);

        $events = [];

        foreach ($newMoonEvents as $event) {
            $eventKey = (string) $event['event_key'];
            if (!in_array($eventKey, $superNewMoonKeys, true)) {
                continue;
            }

            $events[] = $this->formatEvent(
                $event,
                'super_new_moon',
                'Super New Moon',
                'Nov blizko perigea.'
            );
        }

        foreach ($fullMoonEvents as $event) {
            $eventKey = (string) $event['event_key'];

            if (in_array($eventKey, $blueMoonKeys, true)) {
                $events[] = $this->formatEvent(
                    $event,
                    'blue_moon',
                    'Blue Moon',
                    'Druhy spln v jednom kalendarnom mesiaci.'
                );
            }

            if (in_array($eventKey, $microFullMoonKeys, true)) {
                $events[] = $this->formatEvent(
                    $event,
                    'micro_full_moon',
                    'Micro Full Moon',
                    'Spln blizko apogea.'
                );
            }

            if (in_array($eventKey, $superFullMoonKeys, true)) {
                $events[] = $this->formatEvent(
                    $event,
                    'super_full_moon',
                    'Super Full Moon',
                    'Spln blizko perigea.'
                );
            }
        }

        foreach ($newMoonEvents as $event) {
            $eventKey = (string) $event['event_key'];
            if (!in_array($eventKey, $blackMoonKeys, true)) {
                continue;
            }

            $events[] = $this->formatEvent(
                $event,
                'black_moon',
                'Black Moon',
                'Druhy nov v jednom kalendarnom mesiaci.'
            );
        }

        usort($events, static function (array $a, array $b): int {
            $aTs = is_string($a['at'] ?? null) ? strtotime((string) $a['at']) : false;
            $bTs = is_string($b['at'] ?? null) ? strtotime((string) $b['at']) : false;

            if (!is_int($aTs) && !is_int($bTs)) {
                return 0;
            }
            if (!is_int($aTs)) {
                return 1;
            }
            if (!is_int($bTs)) {
                return -1;
            }

            return $aTs <=> $bTs;
        });

        if ($blackMoonKeys === []) {
            $events[] = [
                'key' => 'no_black_moon',
                'label' => 'No Black Moon',
                'at' => null,
                'date' => null,
                'time' => null,
                'note' => sprintf('No Black Moon in %d.', $year),
            ];
        }

        return [
            'year' => $year,
            'timezone' => $resolvedTz,
            'events' => $events,
            'source' => [
                'moon_phases' => [
                    'provider' => 'USNO',
                    'label' => 'USNO Moon Phases API (free, bez API kluca)',
                    'url' => $this->resolveMoonPhasesYearEndpointUrl(),
                    'api_key_required' => false,
                ],
                'distance' => [
                    'provider' => 'JPL',
                    'label' => 'JPL Horizons API',
                    'url' => $this->resolveJplHorizonsEndpointUrl(),
                    'api_key_required' => false,
                ],
            ],
        ];
    }

    /**
     * @return array<int,array{phase:string,at_utc:CarbonImmutable,at_local:CarbonImmutable,event_key:string,month_key:string}>
     */
    private function loadPhaseEvents(int $year, string $tz): array
    {
        $events = [];

        foreach ([$year - 1, $year, $year + 1] as $candidateYear) {
            try {
                $payload = $this->http->getJson(
                    'usno_moon_phases',
                    $this->resolveMoonPhasesYearEndpointUrl(),
                    ['year' => $candidateYear]
                );
            } catch (\Throwable) {
                continue;
            }

            $rows = data_get($payload, 'phasedata');
            if (!is_array($rows)) {
                continue;
            }

            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue;
                }

                $normalized = $this->normalizePhaseRow($row);
                if ($normalized === null) {
                    continue;
                }

                $atLocal = $normalized['at_utc']->setTimezone($tz);
                if ((int) $atLocal->year !== $year) {
                    continue;
                }

                $events[] = [
                    'phase' => $normalized['phase'],
                    'at_utc' => $normalized['at_utc'],
                    'at_local' => $atLocal,
                    'event_key' => $normalized['phase'].'@'.$normalized['at_utc']->format('Y-m-d\TH:i:s\Z'),
                    'month_key' => $atLocal->format('Y-m'),
                ];
            }
        }

        usort($events, static fn (array $a, array $b): int => $a['at_local']->getTimestamp() <=> $b['at_local']->getTimestamp());

        return array_values($events);
    }

    /**
     * @param array<string,mixed> $row
     * @return array{phase:string,at_utc:CarbonImmutable}|null
     */
    private function normalizePhaseRow(array $row): ?array
    {
        $phaseName = strtolower(trim((string) ($row['phase'] ?? '')));
        $phase = match ($phaseName) {
            'new moon' => 'new_moon',
            'full moon' => 'full_moon',
            default => null,
        };

        if ($phase === null) {
            return null;
        }

        $year = is_numeric($row['year'] ?? null) ? (int) $row['year'] : 0;
        $month = is_numeric($row['month'] ?? null) ? (int) $row['month'] : 0;
        $day = is_numeric($row['day'] ?? null) ? (int) $row['day'] : 0;
        $time = trim((string) ($row['time'] ?? ''));

        if ($year < 1700 || !checkdate($month, $day, $year)) {
            return null;
        }

        if (preg_match('/^(?<hour>\d{1,2}):(?<minute>\d{2})$/', $time, $matches) !== 1) {
            return null;
        }

        $hour = (int) ($matches['hour'] ?? -1);
        $minute = (int) ($matches['minute'] ?? -1);
        if ($hour < 0 || $hour > 23 || $minute < 0 || $minute > 59) {
            return null;
        }

        return [
            'phase' => $phase,
            'at_utc' => CarbonImmutable::create($year, $month, $day, $hour, $minute, 0, 'UTC'),
        ];
    }

    /**
     * @param array<int,array{phase:string,at_utc:CarbonImmutable,at_local:CarbonImmutable,event_key:string,month_key:string}> $events
     * @return array<string,float|null>
     */
    private function resolveDistancesKm(array $events): array
    {
        $distances = [];

        foreach ($events as $event) {
            $eventKey = (string) $event['event_key'];
            $timestampKey = $event['at_utc']->format('Y-m-d\TH:i:s\Z');

            if (array_key_exists($timestampKey, $this->distanceCache)) {
                $distances[$eventKey] = $this->distanceCache[$timestampKey];
                continue;
            }

            $distance = $this->fetchMoonDistanceKm($event['at_utc']);
            $this->distanceCache[$timestampKey] = $distance;
            $distances[$eventKey] = $distance;
        }

        return $distances;
    }

    private function fetchMoonDistanceKm(CarbonImmutable $atUtc): ?float
    {
        $providerUrl = $this->resolveJplHorizonsEndpointUrl();
        if ($providerUrl === '') {
            return null;
        }

        try {
            $payload = $this->http->getJson(
                'jpl_horizons_moon',
                $providerUrl,
                [
                    'format' => 'json',
                    'COMMAND' => "'301'",
                    'MAKE_EPHEM' => "'YES'",
                    'EPHEM_TYPE' => "'OBSERVER'",
                    'CENTER' => "'500@399'",
                    'TLIST' => "'".$atUtc->format('Y-m-d H:i')."'",
                    'QUANTITIES' => "'20'",
                ]
            );
        } catch (\Throwable) {
            return null;
        }

        $result = trim((string) ($payload['result'] ?? ''));
        if ($result === '') {
            return null;
        }

        $distanceAu = $this->extractDistanceAuFromHorizonsResult($result);
        if ($distanceAu === null) {
            return null;
        }

        return round($distanceAu * self::AU_IN_KM, 1);
    }

    private function extractDistanceAuFromHorizonsResult(string $result): ?float
    {
        $start = strpos($result, '$$SOE');
        $end = strpos($result, '$$EOE');

        if ($start === false || $end === false || $end <= $start) {
            return null;
        }

        $chunk = substr($result, $start + 5, $end - ($start + 5));
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
        if (count($tokens) < 3) {
            return null;
        }

        for ($index = 2; $index < count($tokens); $index++) {
            $token = (string) ($tokens[$index] ?? '');
            if (preg_match('/^[+-]?\d+(?:\.\d+)?(?:[Ee][+-]?\d+)?$/', $token) !== 1) {
                continue;
            }

            return (float) $token;
        }

        return null;
    }

    /**
     * @param array<int,array{phase:string,at_utc:CarbonImmutable,at_local:CarbonImmutable,event_key:string,month_key:string}> $events
     * @param array<string,float|null> $distancesKm
     * @return array<int,string>
     */
    private function resolveClosestBandKeys(array $events, array $distancesKm): array
    {
        $rows = [];

        foreach ($events as $event) {
            $eventKey = (string) $event['event_key'];
            $distance = $distancesKm[$eventKey] ?? null;
            if (!is_numeric($distance)) {
                continue;
            }

            $rows[] = [
                'event_key' => $eventKey,
                'distance' => (float) $distance,
            ];
        }

        if ($rows === []) {
            return [];
        }

        $distances = array_map(static fn (array $row): float => $row['distance'], $rows);
        $min = min($distances);
        $max = max($distances);
        $threshold = $min + (($max - $min) * self::BAND_RATIO);

        return array_values(array_map(
            static fn (array $row): string => $row['event_key'],
            array_filter(
                $rows,
                static fn (array $row): bool => $row['distance'] <= $threshold
            )
        ));
    }

    /**
     * @param array<int,array{phase:string,at_utc:CarbonImmutable,at_local:CarbonImmutable,event_key:string,month_key:string}> $events
     * @param array<string,float|null> $distancesKm
     * @return array<int,string>
     */
    private function resolveFarthestBandKeys(array $events, array $distancesKm): array
    {
        $rows = [];

        foreach ($events as $event) {
            $eventKey = (string) $event['event_key'];
            $distance = $distancesKm[$eventKey] ?? null;
            if (!is_numeric($distance)) {
                continue;
            }

            $rows[] = [
                'event_key' => $eventKey,
                'distance' => (float) $distance,
            ];
        }

        if ($rows === []) {
            return [];
        }

        $distances = array_map(static fn (array $row): float => $row['distance'], $rows);
        $min = min($distances);
        $max = max($distances);
        $threshold = $max - (($max - $min) * self::BAND_RATIO);

        return array_values(array_map(
            static fn (array $row): string => $row['event_key'],
            array_filter(
                $rows,
                static fn (array $row): bool => $row['distance'] >= $threshold
            )
        ));
    }

    /**
     * @param array<int,array{phase:string,at_utc:CarbonImmutable,at_local:CarbonImmutable,event_key:string,month_key:string}> $events
     * @return array<int,string>
     */
    private function resolveSecondInMonthKeys(array $events): array
    {
        $counts = [];
        $keys = [];

        foreach ($events as $event) {
            $monthKey = (string) $event['month_key'];
            $eventKey = (string) $event['event_key'];

            $counts[$monthKey] = ($counts[$monthKey] ?? 0) + 1;
            if ($counts[$monthKey] === 2) {
                $keys[] = $eventKey;
            }
        }

        return array_values($keys);
    }

    /**
     * @param array{phase:string,at_utc:CarbonImmutable,at_local:CarbonImmutable,event_key:string,month_key:string} $event
     * @return array<string,mixed>
     */
    private function formatEvent(array $event, string $key, string $label, ?string $note = null): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'at' => $event['at_local']->toIso8601String(),
            'date' => $event['at_local']->format('Y-m-d'),
            'time' => $event['at_local']->format('H:i'),
            'note' => $note,
        ];
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

    private function sanitizeTimezone(string $value): string
    {
        $default = (string) config('observing.default_timezone', 'Europe/Bratislava');
        $candidate = trim($value);

        if ($candidate === '') {
            return $default;
        }

        return in_array($candidate, timezone_identifiers_list(), true) ? $candidate : $default;
    }
}

