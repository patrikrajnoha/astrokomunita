<?php

namespace App\Services\Observing\Providers;

use App\Services\Observing\Contracts\SunMoonProvider;
use App\Services\Observing\Support\ObservingHttp;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\Log;

class UsnoSunMoonProvider implements SunMoonProvider
{
    public function __construct(
        private readonly ObservingHttp $http
    ) {
    }

    public function get(float $lat, float $lon, string $date, string $tz): array
    {
        $timeConfig = $this->resolveUsnoTimezone($tz, $date);
        $query = [
            'date' => $date,
            'coords' => number_format($lat, 6, '.', '') . ',' . number_format($lon, 6, '.', ''),
            'tz' => (int) $timeConfig['tz'],
            'dst' => $timeConfig['dst'] ? 'true' : 'false',
        ];

        $payload = $this->http->getJson(
            'usno',
            (string) config('observing.providers.usno_url'),
            $query
        );
        $data = data_get($payload, 'properties.data', []);
        if (!is_array($data)) {
            throw new \RuntimeException('USNO provider payload is invalid.');
        }

        $sundata = is_array($data['sundata'] ?? null) ? $data['sundata'] : [];

        $status = 'ok';
        foreach ($sundata as $row) {
            $phen = strtolower((string) ($row['phen'] ?? ''));
            if (str_contains($phen, 'continuously above the horizon')) {
                $status = 'continuous_day';
                break;
            }
            if (str_contains($phen, 'continuously below the horizon')) {
                $status = 'continuous_night';
                break;
            }
        }

        return [
            'sunrise' => $this->findPhenTime($sundata, 'rise'),
            'sunset' => $this->findPhenTime($sundata, 'set'),
            'civil_twilight_begin' => $this->findPhenTime($sundata, 'begin civil twilight'),
            'civil_twilight_end' => $this->findPhenTime($sundata, 'end civil twilight'),
            'status' => $status,
            'phase_name' => $this->nullableString($data['curphase'] ?? null),
            'fracillum' => $this->parseFraction($data['fracillum'] ?? null),
        ];
    }

    private function resolveUsnoTimezone(string $ianaTimezone, string $date): array
    {
        $fallbackTz = (string) config('observing.default_timezone', 'Europe/Bratislava');
        $tzName = $ianaTimezone !== '' ? $ianaTimezone : $fallbackTz;

        try {
            $zone = new DateTimeZone($tzName);
        } catch (\Throwable $exception) {
            Log::warning('USNO timezone conversion failed, using fallback timezone.', [
                'provider' => 'usno',
                'requested_tz' => $ianaTimezone,
                'date' => $date,
                'exception_message' => $exception->getMessage(),
            ]);

            try {
                $zone = new DateTimeZone($fallbackTz);
            } catch (\Throwable $inner) {
                Log::warning('USNO fallback timezone failed, using UTC offset fallback.', [
                    'provider' => 'usno',
                    'fallback_tz' => $fallbackTz,
                    'date' => $date,
                    'exception_message' => $inner->getMessage(),
                ]);

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

    private function findPhenTime(array $rows, string $needle): ?string
    {
        foreach ($rows as $row) {
            $phen = strtolower((string) ($row['phen'] ?? ''));
            if ($phen !== '' && str_contains($phen, $needle)) {
                $time = $this->nullableString($row['time'] ?? null);
                if ($time !== null) {
                    return $time;
                }
            }
        }

        return null;
    }

    private function parseFraction(mixed $raw): ?float
    {
        if (is_numeric($raw)) {
            $numeric = (float) $raw;
            if ($numeric > 1) {
                return max(0.0, min(1.0, $numeric / 100));
            }
            return max(0.0, min(1.0, $numeric));
        }

        if (!is_string($raw)) {
            return null;
        }

        $normalized = trim(str_replace('%', '', $raw));
        if ($normalized === '' || !is_numeric($normalized)) {
            return null;
        }

        return max(0.0, min(1.0, ((float) $normalized) / 100));
    }

    private function nullableString(mixed $value): ?string
    {
        $str = is_string($value) ? trim($value) : '';
        return $str !== '' ? $str : null;
    }
}
