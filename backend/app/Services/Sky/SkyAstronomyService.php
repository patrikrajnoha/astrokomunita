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
     *   moonrise_at:?string,
     *   moonset_at:?string
     * }
     */
    public function fetch(float $lat, float $lon, string $tz): array
    {
        $localDate = CarbonImmutable::now($tz)->format('Y-m-d');
        $sunMoon = $this->sunMoonProvider->get($lat, $lon, $localDate, $tz);

        $moonrise = null;
        $moonset = null;

        try {
            $skyPayload = $this->skyMicroserviceClient->fetch($lat, $lon, $localDate, $tz);
            $moonPayload = is_array($skyPayload['moon'] ?? null) ? $skyPayload['moon'] : null;
            $moonrise = $this->toIso8601($localDate, $moonPayload['rise_local'] ?? null, $tz);
            $moonset = $this->toIso8601($localDate, $moonPayload['set_local'] ?? null, $tz);
        } catch (\Throwable) {
            // Moonrise/moonset are optional; keep them null when microservice is unavailable.
        }

        $sunrise = $this->toIso8601($localDate, $sunMoon['sunrise'] ?? null, $tz);
        $sunset = $this->toIso8601($localDate, $sunMoon['sunset'] ?? null, $tz);
        $moonPhase = $this->normalizeMoonPhase($sunMoon['phase_name'] ?? null);
        $illumination = $this->normalizeIllumination($sunMoon['fracillum'] ?? null);

        if ($sunrise === null && $sunset === null && $moonPhase === 'unknown') {
            throw new \RuntimeException('Astronomy provider returned no usable data.');
        }

        return [
            'moon_phase' => $moonPhase,
            'moon_illumination_percent' => $illumination,
            'sunrise_at' => $sunrise,
            'sunset_at' => $sunset,
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
}
