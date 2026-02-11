<?php

namespace App\Services\Observing;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Log;

class SkyMicroserviceClient
{
    public function __construct(
        private readonly HttpFactory $http
    ) {
    }

    /**
     * @return array{moon:mixed,planets:array<int,array<string,mixed>>,meta:array<string,mixed>}
     */
    public function fetch(float $lat, float $lon, string $date, string $tz): array
    {
        $baseUrl = $this->resolveBaseUrl();
        $endpointUrl = $this->composeUrl($baseUrl, (string) config('observing.sky_summary.endpoint_path', '/sky-summary'));
        $healthUrl = $this->composeUrl($baseUrl, (string) config('observing.sky_summary.health_path', '/health'));
        $timeout = (int) config('observing.sky_summary.timeout_seconds', 12);
        $query = [
            'lat' => $lat,
            'lon' => $lon,
            'date' => $date,
            'tz' => $tz,
        ];

        try {
            $response = $this->http
                ->timeout($timeout)
                ->retry(
                    (int) config('observing.sky_summary.retry_times', 1),
                    (int) config('observing.sky_summary.retry_sleep_ms', 200)
                )
                ->acceptJson()
                ->get($endpointUrl, $query)
                ->throw();
        } catch (ConnectionException $exception) {
            Log::warning('Sky microservice connection failed.', [
                'base_url' => $baseUrl,
                'full_url' => $this->buildSafeUrl($endpointUrl, $query),
                'timeout_seconds' => $timeout,
                'health_url' => $healthUrl,
                'exception' => $exception->getMessage(),
            ]);

            $hostPort = $this->hostPortFromUrl($baseUrl);
            throw new \RuntimeException(
                "Sky microservice not reachable on {$hostPort}. Start it via: uvicorn main:app --host 127.0.0.1 --port 8010. Open {$healthUrl}"
            );
        } catch (RequestException $exception) {
            Log::warning('Sky microservice request failed.', [
                'base_url' => $baseUrl,
                'full_url' => $this->buildSafeUrl($endpointUrl, $query),
                'timeout_seconds' => $timeout,
                'status' => $exception->response?->status(),
                'health_url' => $healthUrl,
                'exception' => $exception->getMessage(),
            ]);

            throw new \RuntimeException(
                "Sky microservice returned an error status. Open {$healthUrl} and verify /sky-summary endpoint."
            );
        }

        Log::info('Sky microservice request succeeded.', [
            'base_url' => $baseUrl,
            'full_url' => $this->buildSafeUrl($endpointUrl, $query),
            'timeout_seconds' => $timeout,
            'status' => $response->status(),
        ]);

        $decoded = $response->json();

        if (!is_array($decoded)) {
            throw new \RuntimeException('Sky microservice returned an invalid JSON payload.');
        }

        $moon = is_array($decoded['moon'] ?? null) ? $decoded['moon'] : null;
        $planets = is_array($decoded['planets'] ?? null) ? array_values($decoded['planets']) : [];

        return [
            'moon' => $moon,
            'planets' => $planets,
            'meta' => [
                'source' => 'sky_microservice',
            ],
        ];
    }

    private function resolveBaseUrl(): string
    {
        $raw = (string) config('observing.sky_summary.microservice_base', 'http://127.0.0.1:8010');
        $trimmed = rtrim(trim($raw), '/');

        if ($trimmed === '') {
            return 'http://127.0.0.1:8010';
        }

        if (str_contains($trimmed, '/sky-summary')) {
            return preg_replace('#/sky-summary$#', '', $trimmed) ?: 'http://127.0.0.1:8010';
        }

        return $trimmed;
    }

    private function composeUrl(string $baseUrl, string $path): string
    {
        $normalizedPath = '/' . ltrim(trim($path), '/');
        return rtrim($baseUrl, '/') . $normalizedPath;
    }

    private function buildSafeUrl(string $url, array $query): string
    {
        return $url . '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    private function hostPortFromUrl(string $url): string
    {
        $parts = parse_url($url);
        if (!is_array($parts)) {
            return '127.0.0.1:8010';
        }

        $host = (string) ($parts['host'] ?? '127.0.0.1');
        $port = isset($parts['port']) ? ':' . $parts['port'] : '';

        return $host . $port;
    }
}
