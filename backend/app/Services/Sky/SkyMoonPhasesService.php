<?php

namespace App\Services\Sky;

use App\Services\Observing\Contracts\SunMoonProvider;
use App\Services\Observing\Support\ObservingHttp;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

class SkyMoonPhasesService
{
    /** @var array<int,string> */
    private const PHASE_ORDER = [
        'new_moon',
        'waxing_crescent',
        'first_quarter',
        'waxing_gibbous',
        'full_moon',
        'waning_gibbous',
        'last_quarter',
        'waning_crescent',
    ];

    /** @var array<string,string> */
    private const PHASE_LABELS = [
        'new_moon' => 'Nov',
        'waxing_crescent' => 'Dorastajuci kosacik',
        'first_quarter' => 'Prva stvrt',
        'waxing_gibbous' => 'Dorastajuci mesiac',
        'full_moon' => 'Spln',
        'waning_gibbous' => 'Ubudajuci mesiac',
        'last_quarter' => 'Posledna stvrt',
        'waning_crescent' => 'Ubudajuci kosacik',
    ];

    public function __construct(
        private readonly ObservingHttp $http,
        private readonly SunMoonProvider $sunMoonProvider
    ) {
    }

    /**
     * @return array<string,mixed>
     */
    public function fetch(float $lat, float $lon, string $tz, ?string $referenceDate = null): array
    {
        $resolvedTz = $this->sanitizeTimezone($tz);
        $referenceAt = $this->resolveReferenceDate($referenceDate, $resolvedTz);
        $referenceUtc = $referenceAt->setTimezone('UTC');

        $events = $this->loadMajorEventsForWindow((int) $referenceAt->year);
        $major = $this->resolveMajorBounds($events, $referenceUtc);
        $windows = $this->buildPhaseWindows($major);

        $currentPhase = $this->resolveCurrentPhaseFromSunMoonProvider(
            $lat,
            $lon,
            $referenceAt->format('Y-m-d'),
            $resolvedTz
        );

        if ($currentPhase === 'unknown') {
            $currentPhase = $this->resolveCurrentPhaseFromWindows($windows, $referenceUtc);
        }

        $phaseRows = [];

        foreach ($windows as $window) {
            $key = $window['key'];
            $startUtc = $window['start_at'];
            $endUtc = $window['end_at'];
            $startLocal = $startUtc->setTimezone($resolvedTz);
            $endLocal = $endUtc->setTimezone($resolvedTz);
            $hasRange = $endUtc->greaterThan($startUtc);

            $phaseRows[] = [
                'key' => $key,
                'label' => self::PHASE_LABELS[$key] ?? $key,
                'start_at' => $startLocal->toIso8601String(),
                'end_at' => $endLocal->toIso8601String(),
                'start_date' => $startLocal->format('Y-m-d'),
                'end_date' => $hasRange
                    ? $endLocal->subSecond()->format('Y-m-d')
                    : $startLocal->format('Y-m-d'),
                'is_current' => $key === $currentPhase,
            ];
        }

        if (!in_array($currentPhase, self::PHASE_ORDER, true) && $phaseRows !== []) {
            $phaseRows[0]['is_current'] = true;
            $currentPhase = 'new_moon';
        }

        $majorEvents = $this->buildMajorTimeline($events, $referenceUtc, $resolvedTz, $currentPhase);

        return [
            'reference_at' => $referenceAt->toIso8601String(),
            'reference_date' => $referenceAt->format('Y-m-d'),
            'timezone' => $resolvedTz,
            'current_phase' => $currentPhase,
            'phases' => $phaseRows,
            'major_events' => $majorEvents,
            'source' => [
                'provider' => 'USNO',
                'label' => 'USNO Moon Phases API (free, bez API kluca)',
                'url' => $this->resolveYearEndpointUrl(),
                'api_key_required' => false,
            ],
        ];
    }

    /**
     * @return array<int,array{phase:string,at_utc:CarbonImmutable}>
     */
    private function loadMajorEventsForWindow(int $referenceYear): array
    {
        $events = [];
        $years = [$referenceYear - 1, $referenceYear, $referenceYear + 1];

        foreach ($years as $year) {
            $payload = $this->loadMoonPhasesYearPayload($year);
            if (!is_array($payload)) {
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

                $normalized = $this->normalizeMajorEventRow($row);
                if ($normalized !== null) {
                    $events[] = $normalized;
                }
            }
        }

        usort($events, static fn (array $a, array $b): int => $a['at_utc']->getTimestamp() <=> $b['at_utc']->getTimestamp());

        return $events;
    }

    /**
     * @return array<string,mixed>|null
     */
    private function loadMoonPhasesYearPayload(int $year): ?array
    {
        $cacheKey = sprintf('sky_moon_phases_year:v1:%d', $year);
        $ttlMinutes = max(1, (int) config('observing.sky.moon_phases_year_cache_ttl_minutes', 720));
        $cached = Cache::get($cacheKey);

        if (is_array($cached)) {
            return $cached;
        }

        try {
            $payload = $this->http->getJson(
                'usno_moon_phases',
                $this->resolveYearEndpointUrl(),
                ['year' => $year]
            );
        } catch (\Throwable) {
            return null;
        }

        if (is_array($payload)) {
            Cache::put($cacheKey, $payload, now()->addMinutes($ttlMinutes));
        }

        return is_array($payload) ? $payload : null;
    }

    /**
     * @param array<string,mixed> $row
     * @return array{phase:string,at_utc:CarbonImmutable}|null
     */
    private function normalizeMajorEventRow(array $row): ?array
    {
        $phase = $this->normalizeMajorPhaseName($row['phase'] ?? null);
        if ($phase === null) {
            return null;
        }

        $year = is_numeric($row['year'] ?? null) ? (int) $row['year'] : 0;
        $month = is_numeric($row['month'] ?? null) ? (int) $row['month'] : 0;
        $day = is_numeric($row['day'] ?? null) ? (int) $row['day'] : 0;
        $time = trim((string) ($row['time'] ?? ''));

        if ($year < 1900 || !checkdate($month, $day, $year)) {
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

    private function normalizeMajorPhaseName(mixed $value): ?string
    {
        $normalized = strtolower(trim((string) $value));
        $normalized = preg_replace('/\s+/', ' ', $normalized) ?? $normalized;

        if ($normalized === 'new moon') {
            return 'new_moon';
        }

        if ($normalized === 'first quarter') {
            return 'first_quarter';
        }

        if ($normalized === 'full moon') {
            return 'full_moon';
        }

        if ($normalized === 'last quarter') {
            return 'last_quarter';
        }

        return null;
    }

    /**
     * @param array<int,array{phase:string,at_utc:CarbonImmutable}> $events
     * @return array{start:CarbonImmutable,first_quarter:CarbonImmutable,full_moon:CarbonImmutable,last_quarter:CarbonImmutable,end:CarbonImmutable}
     */
    private function resolveMajorBounds(array $events, CarbonImmutable $referenceUtc): array
    {
        $newMoonEvents = array_values(array_filter(
            $events,
            static fn (array $event): bool => $event['phase'] === 'new_moon'
        ));

        if (count($newMoonEvents) < 2) {
            $fallbackStart = $referenceUtc->subDays(15);
            $fallbackEnd = $referenceUtc->addDays(15);

            return [
                'start' => $fallbackStart,
                'first_quarter' => $fallbackStart->addDays(7),
                'full_moon' => $fallbackStart->addDays(14),
                'last_quarter' => $fallbackStart->addDays(22),
                'end' => $fallbackEnd,
            ];
        }

        $start = null;
        foreach ($newMoonEvents as $event) {
            if ($event['at_utc']->lessThanOrEqualTo($referenceUtc)) {
                $start = $event['at_utc'];
                continue;
            }

            break;
        }

        if (!$start instanceof CarbonImmutable) {
            $start = $newMoonEvents[0]['at_utc'];
        }

        $end = null;
        foreach ($newMoonEvents as $event) {
            if ($event['at_utc']->greaterThan($start)) {
                $end = $event['at_utc'];
                break;
            }
        }

        if (!$end instanceof CarbonImmutable || !$end->greaterThan($start)) {
            $end = $start->addDays(30);
        }

        $firstQuarter = $this->findPhaseBetween($events, 'first_quarter', $start, $end) ?? $start->addDays(7);
        $fullMoon = $this->findPhaseBetween($events, 'full_moon', $start, $end) ?? $start->addDays(14);
        $lastQuarter = $this->findPhaseBetween($events, 'last_quarter', $start, $end) ?? $start->addDays(22);

        return [
            'start' => $start,
            'first_quarter' => $firstQuarter,
            'full_moon' => $fullMoon,
            'last_quarter' => $lastQuarter,
            'end' => $end,
        ];
    }

    /**
     * @param array<int,array{phase:string,at_utc:CarbonImmutable}> $events
     */
    private function findPhaseBetween(
        array $events,
        string $phaseKey,
        CarbonImmutable $start,
        CarbonImmutable $end
    ): ?CarbonImmutable {
        foreach ($events as $event) {
            if ($event['phase'] !== $phaseKey) {
                continue;
            }

            $at = $event['at_utc'];
            if ($at->greaterThanOrEqualTo($start) && $at->lessThanOrEqualTo($end)) {
                return $at;
            }
        }

        return null;
    }

    /**
     * @param array{start:CarbonImmutable,first_quarter:CarbonImmutable,full_moon:CarbonImmutable,last_quarter:CarbonImmutable,end:CarbonImmutable} $major
     * @return array<int,array{key:string,start_at:CarbonImmutable,end_at:CarbonImmutable}>
     */
    private function buildPhaseWindows(array $major): array
    {
        $newMoon = $major['start'];
        $firstQuarter = $major['first_quarter'];
        $fullMoon = $major['full_moon'];
        $lastQuarter = $major['last_quarter'];
        $nextNewMoon = $major['end'];

        return [
            ['key' => 'new_moon', 'start_at' => $newMoon, 'end_at' => $newMoon],
            ['key' => 'waxing_crescent', 'start_at' => $newMoon, 'end_at' => $firstQuarter],
            ['key' => 'first_quarter', 'start_at' => $firstQuarter, 'end_at' => $firstQuarter],
            ['key' => 'waxing_gibbous', 'start_at' => $firstQuarter, 'end_at' => $fullMoon],
            ['key' => 'full_moon', 'start_at' => $fullMoon, 'end_at' => $fullMoon],
            ['key' => 'waning_gibbous', 'start_at' => $fullMoon, 'end_at' => $lastQuarter],
            ['key' => 'last_quarter', 'start_at' => $lastQuarter, 'end_at' => $lastQuarter],
            ['key' => 'waning_crescent', 'start_at' => $lastQuarter, 'end_at' => $nextNewMoon],
        ];
    }

    /**
     * @param array<int,array{phase:string,at_utc:CarbonImmutable}> $events
     * @return array<int,array{key:string,label:string,at:string,date:string,time:string,is_current:bool}>
     */
    private function buildMajorTimeline(
        array $events,
        CarbonImmutable $referenceUtc,
        string $tz,
        string $currentPhase
    ): array {
        $majorEvents = array_values(array_filter(
            $events,
            static fn (array $event): bool => in_array($event['phase'], [
                'new_moon',
                'first_quarter',
                'full_moon',
                'last_quarter',
            ], true)
        ));

        if ($majorEvents === []) {
            return [];
        }

        $firstFutureIndex = null;

        foreach ($majorEvents as $index => $event) {
            if ($event['at_utc']->greaterThan($referenceUtc)) {
                $firstFutureIndex = $index;
                break;
            }
        }

        $startIndex = $firstFutureIndex === null
            ? max(0, count($majorEvents) - 1)
            : max(0, $firstFutureIndex - 1);

        $timeline = [];

        for ($offset = 0; $offset < 4; $offset++) {
            $entry = $majorEvents[$startIndex + $offset] ?? null;
            if (!is_array($entry)) {
                break;
            }

            $atLocal = $entry['at_utc']->setTimezone($tz);
            $key = $entry['phase'];

            $timeline[] = [
                'key' => $key,
                'label' => self::PHASE_LABELS[$key] ?? $key,
                'at' => $atLocal->toIso8601String(),
                'date' => $atLocal->format('Y-m-d'),
                'time' => $atLocal->format('H:i'),
                'is_current' => $key === $currentPhase,
            ];
        }

        return $timeline;
    }

    /**
     * @param array<int,array{key:string,start_at:CarbonImmutable,end_at:CarbonImmutable}> $windows
     */
    private function resolveCurrentPhaseFromWindows(array $windows, CarbonImmutable $referenceUtc): string
    {
        foreach ($windows as $window) {
            if (!$window['end_at']->greaterThan($window['start_at'])) {
                continue;
            }

            if ($referenceUtc->greaterThanOrEqualTo($window['start_at']) && $referenceUtc->lessThan($window['end_at'])) {
                return $window['key'];
            }
        }

        return 'unknown';
    }

    private function resolveCurrentPhaseFromSunMoonProvider(
        float $lat,
        float $lon,
        string $date,
        string $tz
    ): string {
        try {
            $payload = $this->sunMoonProvider->get($lat, $lon, $date, $tz);
        } catch (\Throwable) {
            return 'unknown';
        }

        return $this->normalizeCurrentPhaseName($payload['phase_name'] ?? null);
    }

    private function normalizeCurrentPhaseName(mixed $value): string
    {
        $raw = is_string($value) ? trim(mb_strtolower($value)) : '';
        if ($raw === '') {
            return 'unknown';
        }

        return match ($raw) {
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

    private function resolveYearEndpointUrl(): string
    {
        $configured = trim((string) config(
            'events.nasa_watch_the_skies.moon_phases_year_url',
            config('events.nasa_watch_the_skies.url', 'https://aa.usno.navy.mil/api/moon/phases/year')
        ));

        return $configured !== '' ? $configured : 'https://aa.usno.navy.mil/api/moon/phases/year';
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

    private function resolveReferenceDate(?string $referenceDate, string $tz): CarbonImmutable
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
}
