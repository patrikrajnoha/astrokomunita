<?php

namespace App\Support\Sky;

use Illuminate\Http\Request;

class SkyContextResolver
{
    /**
     * @param array<string,mixed> $validated
     * @return array{lat:float,lon:float,tz:string,coordinate_source:string,timezone_source:string}
     */
    public function resolve(Request $request, array $validated = []): array
    {
        $queryLat = $this->toValidLat($validated['lat'] ?? null);
        $queryLon = $this->toValidLon($validated['lon'] ?? null);

        $coordinateSource = 'fallback_config';
        if ($queryLat !== null && $queryLon !== null) {
            $lat = $queryLat;
            $lon = $queryLon;
            $coordinateSource = 'query';
        } else {
            $userCoords = $this->resolveUserCoordinates($request);

            if ($userCoords !== null) {
                $lat = $userCoords['lat'];
                $lon = $userCoords['lon'];
                $coordinateSource = 'user_canonical_location';
            } else {
                $fallback = $this->fallbackCoordinates();
                $lat = $fallback['lat'];
                $lon = $fallback['lon'];
            }
        }

        $timezoneSource = 'fallback_config';
        $queryTz = $this->resolveValidTimezone($validated['tz'] ?? null);
        if ($queryTz !== null) {
            $tz = $queryTz;
            $timezoneSource = 'query';
        } else {
            $userTz = $this->resolveUserTimezone($request);
            if ($userTz !== null) {
                $tz = $userTz;
                $timezoneSource = 'user_canonical_location';
            } else {
                $tz = $this->fallbackTimezone();
            }
        }

        return [
            'lat' => round($lat, 6),
            'lon' => round($lon, 6),
            'tz' => $tz,
            'coordinate_source' => $coordinateSource,
            'timezone_source' => $timezoneSource,
        ];
    }

    /**
     * @return array{lat:float,lon:float}|null
     */
    private function resolveUserCoordinates(Request $request): ?array
    {
        $user = $request->user();
        if ($user === null) {
            return null;
        }

        $locationData = is_array($user->location_data ?? null) ? $user->location_data : [];
        $dataLat = $this->toValidLat($locationData['latitude'] ?? null);
        $dataLon = $this->toValidLon($locationData['longitude'] ?? null);
        if ($dataLat !== null && $dataLon !== null) {
            return ['lat' => $dataLat, 'lon' => $dataLon];
        }

        $locationMeta = is_array($user->location_meta ?? null) ? $user->location_meta : [];
        $metaLat = $this->toValidLat($locationMeta['lat'] ?? null);
        $metaLon = $this->toValidLon($locationMeta['lon'] ?? null);
        if ($metaLat !== null && $metaLon !== null) {
            return ['lat' => $metaLat, 'lon' => $metaLon];
        }

        return null;
    }

    private function resolveUserTimezone(Request $request): ?string
    {
        $user = $request->user();
        if ($user === null) {
            return null;
        }

        $locationData = is_array($user->location_data ?? null) ? $user->location_data : [];
        $fromData = $this->resolveValidTimezone($locationData['timezone'] ?? null);
        if ($fromData !== null) {
            return $fromData;
        }

        $locationMeta = is_array($user->location_meta ?? null) ? $user->location_meta : [];
        $fromMeta = $this->resolveValidTimezone($locationMeta['tz'] ?? null);
        if ($fromMeta !== null) {
            return $fromMeta;
        }

        return $this->resolveValidTimezone($user->timezone ?? null);
    }

    /**
     * @return array{lat:float,lon:float}
     */
    private function fallbackCoordinates(): array
    {
        $lat = $this->toValidLat(config('observing.sky_context.fallback_lat', 48.1486)) ?? 48.1486;
        $lon = $this->toValidLon(config('observing.sky_context.fallback_lon', 17.1077)) ?? 17.1077;

        return [
            'lat' => $lat,
            'lon' => $lon,
        ];
    }

    private function fallbackTimezone(): string
    {
        $fallback = $this->resolveValidTimezone(config('observing.sky_context.fallback_tz', null));
        if ($fallback !== null) {
            return $fallback;
        }

        $observingDefault = $this->resolveValidTimezone(config('observing.default_timezone', null));
        if ($observingDefault !== null) {
            return $observingDefault;
        }

        return 'Europe/Bratislava';
    }

    private function resolveValidTimezone(mixed $value): ?string
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

    private function toValidLat(mixed $value): ?float
    {
        if (!is_numeric($value)) {
            return null;
        }

        $lat = (float) $value;
        if ($lat < -90 || $lat > 90) {
            return null;
        }

        return $lat;
    }

    private function toValidLon(mixed $value): ?float
    {
        if (!is_numeric($value)) {
            return null;
        }

        $lon = (float) $value;
        if ($lon < -180 || $lon > 180) {
            return null;
        }

        return $lon;
    }
}
