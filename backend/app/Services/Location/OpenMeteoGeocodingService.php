<?php

namespace App\Services\Location;

use Illuminate\Http\Client\Factory as HttpFactory;

class OpenMeteoGeocodingService
{
    public function __construct(
        private readonly HttpFactory $http
    ) {
    }

    /**
     * @return array<int, array{
     *   label:string,
     *   place_id:string,
     *   lat:float,
     *   lon:float,
     *   timezone:?string,
     *   country:?string
     * }>
     */
    public function search(string $query, int $limit = 8): array
    {
        $needle = trim($query);
        if ($needle === '') {
            return [];
        }

        $providerUrl = trim((string) config('observing.providers.open_meteo_geocoding_url', ''));
        if ($providerUrl === '') {
            return [];
        }

        $count = max(1, min($limit, 10));

        try {
            $response = $this->http
                ->timeout(6)
                ->acceptJson()
                ->get($providerUrl, [
                    'name' => $needle,
                    'count' => $count,
                    'language' => 'sk',
                    'format' => 'json',
                ]);
        } catch (\Throwable) {
            return [];
        }

        if (!$response->successful()) {
            return [];
        }

        $payload = $response->json();
        $results = is_array($payload['results'] ?? null) ? $payload['results'] : [];

        $rows = [];

        foreach ($results as $row) {
            if (!is_array($row)) {
                continue;
            }

            $name = $this->sanitizeLabel($row['name'] ?? null);
            $lat = $this->toFloat($row['latitude'] ?? null);
            $lon = $this->toFloat($row['longitude'] ?? null);

            if ($name === null || $lat === null || $lon === null) {
                continue;
            }

            $admin = $this->sanitizeLabel($row['admin1'] ?? null);
            $country = $this->sanitizeLabel($row['country'] ?? null);
            $countryCode = $this->sanitizeCountryCode($row['country_code'] ?? null);
            $timezone = $this->sanitizeTimezone($row['timezone'] ?? null);
            $id = is_numeric($row['id'] ?? null) ? (string) (int) $row['id'] : null;

            $labelParts = [$name];
            if ($admin !== null && mb_strtolower($admin) !== mb_strtolower($name)) {
                $labelParts[] = $admin;
            }
            if ($country !== null) {
                $labelParts[] = $country;
            }

            $placeId = $id !== null
                ? 'open_meteo:' . $id
                : 'open_meteo:' . md5($name . '|' . number_format($lat, 4, '.', '') . '|' . number_format($lon, 4, '.', ''));

            $rows[] = [
                'label' => implode(', ', $labelParts),
                'place_id' => $placeId,
                'lat' => round($lat, 6),
                'lon' => round($lon, 6),
                'timezone' => $timezone,
                'country' => $countryCode,
            ];
        }

        return array_values($rows);
    }

    private function sanitizeLabel(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $trimmed = trim($value);
        return $trimmed !== '' ? $trimmed : null;
    }

    private function sanitizeTimezone(mixed $value): ?string
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

    private function sanitizeCountryCode(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $trimmed = strtoupper(trim($value));
        return preg_match('/^[A-Z]{2}$/', $trimmed) === 1 ? $trimmed : null;
    }

    private function toFloat(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }
}
