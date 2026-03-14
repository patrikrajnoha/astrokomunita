<?php

namespace App\Services\Sky;

use App\Services\Observing\Contracts\SunMoonProvider;
use App\Services\Observing\SkyMicroserviceClient;
use Carbon\CarbonImmutable;

class SkyAstronomyService
{
    public function __construct(
        private readonly SunMoonProvider $sunMoonProvider,
        private readonly SkyMicroserviceClient $skyMicroserviceClient
    ) {
    }

    /**
     * @return array{
     *   moon_phase:string,
     *   moon_illumination_percent:?int,
     *   sunrise_at:?string,
     *   sunset_at:?string,
     *   civil_twilight_end_at:?string,
     *   sun_altitude_deg:?float,
     *   moon_altitude_deg:?float,
     *   sample_at:?string,
     *   moonrise_at:?string,
     *   moonset_at:?string
     * }
     */
    public function fetch(float $lat, float $lon, string $tz): array
    {
        $localDate = CarbonImmutable::now($tz)->format('Y-m-d');
        $sunMoon = [];

        try {
            $sunMoon = $this->sunMoonProvider->get($lat, $lon, $localDate, $tz);
        } catch (\Throwable) {
            // Degraded mode: keep unknown/null astronomy fields when sun/moon provider is unavailable.
        }

        $moonrise = null;
        $moonset = null;
        $sunAltitude = null;
        $moonAltitude = null;
        $sampleAt = null;

        try {
            $skyPayload = $this->skyMicroserviceClient->fetch($lat, $lon, $localDate, $tz);
            $moonPayload = is_array($skyPayload['moon'] ?? null) ? $skyPayload['moon'] : null;
            $moonrise = $this->normalizeOptionalIso8601(
                $this->toIso8601($localDate, $moonPayload['rise_local'] ?? null, $tz)
            );
            $moonset = $this->normalizeOptionalIso8601(
                $this->toIso8601($localDate, $moonPayload['set_local'] ?? null, $tz)
            );
            $sunAltitude = $this->normalizeSunAltitude($skyPayload['sun_altitude_deg'] ?? null);
            $sampleAt = $this->normalizeSampleAt($skyPayload['sample_at'] ?? null, $tz);
            $moonAltitude = $this->resolveMoonAltitude($moonPayload['altitude_hourly'] ?? null, $sampleAt, $tz);
        } catch (\Throwable) {
            // Moonrise/moonset are optional; keep them null when microservice is unavailable.
        }

        $sunrise = $this->normalizeOptionalIso8601($this->toIso8601($localDate, $sunMoon['sunrise'] ?? null, $tz));
        $sunset = $this->normalizeOptionalIso8601($this->toIso8601($localDate, $sunMoon['sunset'] ?? null, $tz));
        $civilTwilightEnd = $this->normalizeOptionalIso8601(
            $this->toIso8601($localDate, $sunMoon['civil_twilight_end'] ?? null, $tz)
        );
        $moonPhase = $this->normalizeMoonPhase($sunMoon['phase_name'] ?? null);
        $illumination = $this->normalizeIllumination($sunMoon['fracillum'] ?? null);

        return [
            'moon_phase' => $moonPhase,
            'moon_illumination_percent' => $illumination,
            'sunrise_at' => $sunrise,
            'sunset_at' => $sunset,
            'civil_twilight_end_at' => $civilTwilightEnd,
            'sun_altitude_deg' => $sunAltitude,
            'moon_altitude_deg' => $moonAltitude,
            'sample_at' => $sampleAt,
            'moonrise_at' => $moonrise,
            'moonset_at' => $moonset,
        ];
    }

    private function normalizeMoonPhase(mixed $value): string
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

    private function normalizeIllumination(mixed $value): ?int
    {
        if (!is_numeric($value)) {
            return null;
        }

        $fraction = (float) $value;
        if ($fraction > 1) {
            $fraction = $fraction / 100;
        }

        return (int) round(max(0.0, min(1.0, $fraction)) * 100);
    }

    private function toIso8601(string $date, mixed $time, string $tz): ?string
    {
        if (!is_string($time)) {
            return null;
        }

        $value = trim($time);
        if (!preg_match('/^\d{2}:\d{2}$/', $value)) {
            return null;
        }

        $local = CarbonImmutable::createFromFormat('Y-m-d H:i', "{$date} {$value}", $tz);
        if ($local === false) {
            return null;
        }

        return $local->toIso8601String();
    }

    private function normalizeOptionalIso8601(?string $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed !== '' ? $trimmed : null;
    }

    private function normalizeSunAltitude(mixed $value): ?float
    {
        if (!is_numeric($value)) {
            return null;
        }

        return round(max(-90.0, min(90.0, (float) $value)), 1);
    }

    private function resolveMoonAltitude(mixed $hourlyPayload, ?string $sampleAt, string $tz): ?float
    {
        if (!is_array($hourlyPayload) || $hourlyPayload === []) {
            return null;
        }

        $targetMinutes = $this->resolveTargetMinutes($sampleAt, $tz);
        $bestAltitude = null;
        $bestDelta = null;

        foreach ($hourlyPayload as $point) {
            if (!is_array($point)) {
                continue;
            }

            $time = trim((string) ($point['local_time'] ?? ''));
            if (!preg_match('/^\d{2}:\d{2}$/', $time)) {
                continue;
            }

            if (!is_numeric($point['altitude_deg'] ?? null)) {
                continue;
            }

            [$hours, $minutes] = array_map('intval', explode(':', $time, 2));
            $pointMinutes = ($hours * 60) + $minutes;
            $delta = abs($pointMinutes - $targetMinutes);

            if ($bestDelta === null || $delta < $bestDelta) {
                $bestDelta = $delta;
                $bestAltitude = round(max(-90.0, min(90.0, (float) $point['altitude_deg'])), 1);
            }
        }

        return $bestAltitude;
    }

    private function resolveTargetMinutes(?string $sampleAt, string $tz): int
    {
        try {
            $target = $sampleAt
                ? CarbonImmutable::parse($sampleAt, $tz)->setTimezone($tz)
                : CarbonImmutable::now($tz);
        } catch (\Throwable) {
            $target = CarbonImmutable::now($tz);
        }

        return ($target->hour * 60) + $target->minute;
    }

    private function normalizeSampleAt(mixed $value, string $tz): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($trimmed, $tz)->toIso8601String();
        } catch (\Throwable) {
            return null;
        }
    }
}
