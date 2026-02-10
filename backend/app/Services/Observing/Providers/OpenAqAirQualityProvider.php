<?php

namespace App\Services\Observing\Providers;

use App\Services\Observing\Contracts\AirQualityProvider;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class OpenAqAirQualityProvider implements AirQualityProvider
{
    public function fetch(float $lat, float $lon): array
    {
        $apiKey = trim((string) config('observing.providers.openaq_api_key'));
        if ($apiKey === '') {
            return $this->unavailable();
        }

        $locations = $this->httpClient($apiKey)->get(config('observing.providers.openaq_locations_url'), [
            'coordinates' => "{$lat},{$lon}",
            'radius' => (int) config('observing.providers.openaq_radius_meters', 25000),
            'limit' => 5,
            'sort' => 'distance',
        ]);

        if (!$locations->successful()) {
            throw new \RuntimeException('OpenAQ locations request failed.');
        }

        $locationRows = data_get($locations->json(), 'results', data_get($locations->json(), 'data', []));
        if (!is_array($locationRows) || count($locationRows) === 0) {
            return $this->unavailable();
        }

        $location = $this->pickClosestLocation($locationRows);
        $locationId = isset($location['id']) && is_numeric($location['id']) ? (int) $location['id'] : null;

        if ($locationId === null) {
            return $this->unavailable();
        }

        $latestUrl = str_replace(
            '{id}',
            (string) $locationId,
            (string) config('observing.providers.openaq_latest_url', 'https://api.openaq.org/v3/locations/{id}/latest')
        );

        $latest = $this->httpClient($apiKey)->get($latestUrl);

        if (!$latest->successful()) {
            throw new \RuntimeException('OpenAQ latest request failed.');
        }

        [$pm25, $pm10] = $this->extractPmValues($latest->json());

        return [
            'pm25' => $pm25,
            'pm10' => $pm10,
            'source' => 'OpenAQ',
            'status' => ($pm25 === null && $pm10 === null) ? 'unavailable' : 'ok',
        ];
    }

    private function httpClient(string $apiKey): PendingRequest
    {
        return Http::timeout((int) config('observing.http.timeout_seconds', 8))
            ->retry((int) config('observing.http.retry_times', 2), (int) config('observing.http.retry_sleep_ms', 200))
            ->acceptJson()
            ->withHeaders([
                'X-API-Key' => $apiKey,
            ]);
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
