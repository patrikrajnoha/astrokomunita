<?php

namespace App\Services\Observing\Providers;

use App\Services\Observing\Contracts\AirQualityProvider;
use App\Services\Observing\Support\ObservingHttp;

class OpenAqAirQualityProvider implements AirQualityProvider
{
    public function __construct(
        private readonly ObservingHttp $http
    ) {
    }

    public function get(float $lat, float $lon, string $date, string $tz): array
    {
        $apiKey = trim((string) config('observing.providers.openaq.key'));
        if ($apiKey === '') {
            return $this->unavailable();
        }

        $baseUrl = rtrim((string) config('observing.providers.openaq.base_url', 'https://api.openaq.org/v3'), '/');
        $locationsPayload = $this->http->getJson(
            'openaq',
            "{$baseUrl}/locations",
            [
                'coordinates' => number_format($lat, 6, '.', '') . ',' . number_format($lon, 6, '.', ''),
                'radius' => (int) config('observing.providers.openaq_radius_meters', 25000),
                'limit' => 5,
                'sort' => 'distance',
            ],
            [
                'X-API-Key' => $apiKey,
            ]
        );

        $locationRows = data_get($locationsPayload, 'results', data_get($locationsPayload, 'data', []));
        if (!is_array($locationRows) || count($locationRows) === 0) {
            return $this->unavailable();
        }

        $location = $this->pickClosestLocation($locationRows);
        $locationId = isset($location['id']) && is_numeric($location['id']) ? (int) $location['id'] : null;

        if ($locationId === null) {
            return $this->unavailable();
        }

        $latestPayload = $this->http->getJson(
            'openaq',
            "{$baseUrl}/locations/{$locationId}/latest",
            [],
            [
                'X-API-Key' => $apiKey,
            ]
        );

        [$pm25, $pm10] = $this->extractPmValues($latestPayload);

        return [
            'pm25' => $pm25,
            'pm10' => $pm10,
            'source' => 'OpenAQ',
            'status' => ($pm25 === null && $pm10 === null) ? 'unavailable' : 'ok',
        ];
    }

    private function pickClosestLocation(array $rows): array
    {
        $best = null;
        $bestDistance = null;

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $distance = $row['distance'] ?? data_get($row, 'coordinates.distance');
            if (!is_numeric($distance)) {
                if ($best === null) {
                    $best = $row;
                }
                continue;
            }

            $distanceValue = (float) $distance;
            if ($bestDistance === null || $distanceValue < $bestDistance) {
                $best = $row;
                $bestDistance = $distanceValue;
            }
        }

        return is_array($best) ? $best : $rows[0];
    }

    private function extractPmValues(mixed $payload): array
    {
        $foundPm25 = null;
        $foundPm10 = null;

        $walk = function ($node) use (&$walk, &$foundPm25, &$foundPm10): void {
            if (!is_array($node)) {
                return;
            }

            $parameter = '';
            $parameterName = data_get($node, 'parameter.name', $node['parameter'] ?? ($node['name'] ?? null));
            if (is_string($parameterName)) {
                $parameter = strtolower(trim($parameterName));
            }

            $value = $node['value'] ?? data_get($node, 'summary.avg');
            if (is_numeric($value) && $parameter !== '') {
                $numericValue = round((float) $value, 1);

                if ($foundPm25 === null && (str_contains($parameter, 'pm2.5') || str_contains($parameter, 'pm25'))) {
                    $foundPm25 = $numericValue;
                }

                if ($foundPm10 === null && str_contains($parameter, 'pm10')) {
                    $foundPm10 = $numericValue;
                }
            }

            foreach ($node as $child) {
                if (is_array($child)) {
                    $walk($child);
                }
            }
        };

        if (is_array($payload)) {
            $walk($payload);
        }

        return [$foundPm25, $foundPm10];
    }

    private function unavailable(): array
    {
        return [
            'pm25' => null,
            'pm10' => null,
            'source' => 'OpenAQ',
            'status' => 'unavailable',
        ];
    }
}
