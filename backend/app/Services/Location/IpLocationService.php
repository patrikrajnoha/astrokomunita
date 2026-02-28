<?php

namespace App\Services\Location;

use Illuminate\Http\Client\Factory as HttpFactory;

class IpLocationService
{
    public function __construct(
        private readonly HttpFactory $http
    ) {
    }

    /**
     * @return array{
     *   country:string,
     *   city:string,
     *   approx_lat:float,
     *   approx_lon:float,
     *   timezone:string
     * }|null
     */
    public function lookup(?string $ip): ?array
    {
        $sanitizedIp = $this->sanitizeIp($ip);
        $url = 'https://ipwho.is' . ($sanitizedIp !== null ? '/' . $sanitizedIp : '');

        try {
            $response = $this->http
                ->timeout(5)
                ->acceptJson()
                ->get($url);
        } catch (\Throwable) {
            return $this->fallbackPayload();
        }

        if (!$response->successful()) {
            return $this->fallbackPayload();
        }

        $payload = $response->json();
        if (!is_array($payload)) {
            return $this->fallbackPayload();
        }

        if (($payload['success'] ?? false) !== true) {
            return $this->fallbackPayload();
        }

        $lat = $this->toFloat($payload['latitude'] ?? null);
        $lon = $this->toFloat($payload['longitude'] ?? null);
        $timezone = $this->normalizeTimezone(
            is_array($payload['timezone'] ?? null)
                ? ($payload['timezone']['id'] ?? null)
                : ($payload['timezone'] ?? null)
        );

        if ($lat === null || $lon === null || $timezone === null) {
            return $this->fallbackPayload();
        }

        return [
            'country' => $this->sanitizeLabel($payload['country'] ?? 'Unknown'),
            'city' => $this->sanitizeLabel($payload['city'] ?? 'Unknown'),
            'approx_lat' => round($lat, 6),
            'approx_lon' => round($lon, 6),
            'timezone' => $timezone,
        ];
    }

    /**
     * @return array{
     *   country:string,
     *   city:string,
     *   approx_lat:float,
     *   approx_lon:float,
     *   timezone:string
     * }
     */
    private function fallbackPayload(): array
    {
        $lat = $this->toFloat(config('observing.sky_context.fallback_lat', 48.1486)) ?? 48.1486;
        $lon = $this->toFloat(config('observing.sky_context.fallback_lon', 17.1077)) ?? 17.1077;
        $timezone = $this->normalizeTimezone(config('observing.sky_context.fallback_tz'))
            ?? $this->normalizeTimezone(config('observing.default_timezone'))
            ?? 'Europe/Bratislava';

        return [
            'country' => 'Unknown',
            'city' => 'Unknown',
            'approx_lat' => round($lat, 6),
            'approx_lon' => round($lon, 6),
            'timezone' => $timezone,
        ];
    }

    private function sanitizeIp(?string $ip): ?string
    {
        if (!is_string($ip) || trim($ip) === '') {
            return null;
        }

        $candidate = trim($ip);
        if (filter_var($candidate, FILTER_VALIDATE_IP) === false) {
            return null;
        }

        return $candidate;
    }

    private function normalizeTimezone(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        return in_array($trimmed, timezone_identifiers_list(), true) ? $trimmed : null;
    }

    private function sanitizeLabel(mixed $value): string
    {
        $label = is_string($value) ? trim($value) : '';
        return $label !== '' ? $label : 'Unknown';
    }

    private function toFloat(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }
}
