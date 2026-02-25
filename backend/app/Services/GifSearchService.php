<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class GifSearchService
{
    /**
     * @return array{data:array<int,array<string,mixed>>,meta:array<string,mixed>}
     */
    public function search(string $query, int $limit = 20, int $offset = 0): array
    {
        $normalizedQuery = trim($query);
        if (mb_strlen($normalizedQuery) < 2) {
            return [
                'data' => [],
                'meta' => ['total_count' => 0, 'count' => 0, 'offset' => $offset],
            ];
        }

        $limit = max(1, min($limit, 50));
        $offset = max(0, $offset);
        $cacheKey = $this->cacheKey($normalizedQuery, $limit, $offset);
        $ttlSeconds = max(60, (int) config('services.giphy.cache_ttl_seconds', 600));

        return Cache::remember($cacheKey, $ttlSeconds, function () use ($normalizedQuery, $limit, $offset) {
            $this->consumeGlobalQuota();

            $apiKey = trim((string) config('services.giphy.api_key', ''));
            if ($apiKey === '') {
                throw new RuntimeException('GIF search is unavailable (missing provider key).', 503);
            }

            $baseUrl = rtrim((string) config('services.giphy.base_url', 'https://api.giphy.com/v1'), '/');
            $timeoutSeconds = max(1, (int) config('services.giphy.timeout_seconds', 4));
            $connectTimeoutSeconds = max(1, (int) config('services.giphy.connect_timeout_seconds', 2));

            try {
                $response = Http::secure()
                    ->acceptJson()
                    ->connectTimeout($connectTimeoutSeconds)
                    ->timeout($timeoutSeconds)
                    ->get($baseUrl . '/gifs/search', [
                        'api_key' => $apiKey,
                        'q' => $normalizedQuery,
                        'limit' => $limit,
                        'offset' => $offset,
                        'rating' => 'pg-13',
                    ]);
            } catch (ConnectionException $exception) {
                throw new RuntimeException('GIF search timed out. Please try again later.', 504, $exception);
            }

            $this->logProviderRateHeaders($response->headers());

            if ($response->status() === 429) {
                throw new RuntimeException('GIF provider rate limit reached. Please try again later.', 429);
            }

            if ($response->status() === 401 || $response->status() === 403) {
                throw new RuntimeException('GIF provider key is invalid or blocked.', 503);
            }

            if (!$response->successful()) {
                throw new RuntimeException('GIF provider is currently unavailable.', 503);
            }

            $payload = $response->json();
            $rows = is_array($payload['data'] ?? null) ? $payload['data'] : [];
            $meta = is_array($payload['pagination'] ?? null) ? $payload['pagination'] : [];

            return [
                'data' => $this->mapRowsToContract($rows),
                'meta' => [
                    'total_count' => (int) ($meta['total_count'] ?? 0),
                    'count' => (int) ($meta['count'] ?? 0),
                    'offset' => (int) ($meta['offset'] ?? 0),
                ],
            ];
        });
    }

    private function cacheKey(string $query, int $limit, int $offset): string
    {
        return 'integrations:gifs:search:' . sha1(mb_strtolower($query) . '|' . $limit . '|' . $offset);
    }

    private function consumeGlobalQuota(): void
    {
        $maxPerHour = max(1, (int) config('services.giphy.global_hourly_limit', 80));
        $window = now()->format('YmdH');
        $key = 'integrations:gifs:global-hourly:' . $window;

        $count = Cache::increment($key);
        if ($count === 1) {
            Cache::put($key, 1, now()->endOfHour()->addSecond());
        }

        if ($count > $maxPerHour) {
            throw new RuntimeException('GIF search docasne nedostupny', 429);
        }
    }

    /**
     * @param array<int,mixed> $rows
     * @return array<int,array<string,mixed>>
     */
    private function mapRowsToContract(array $rows): array
    {
        $mapped = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $id = (string) ($row['id'] ?? '');
            if ($id === '') {
                continue;
            }

            $images = is_array($row['images'] ?? null) ? $row['images'] : [];
            $preview = is_array($images['fixed_width_small'] ?? null)
                ? $images['fixed_width_small']
                : (is_array($images['preview_gif'] ?? null) ? $images['preview_gif'] : null);
            $original = is_array($images['original'] ?? null) ? $images['original'] : null;

            $previewUrl = $this->sanitizeMediaUrl($preview['url'] ?? null);
            $originalUrl = $this->sanitizeMediaUrl($original['url'] ?? null);
            if (!$previewUrl || !$originalUrl) {
                continue;
            }

            $mapped[] = [
                'id' => $id,
                'title' => trim((string) ($row['title'] ?? '')),
                'preview_url' => $previewUrl,
                'original_url' => $originalUrl,
                'width' => (int) ($original['width'] ?? 0),
                'height' => (int) ($original['height'] ?? 0),
            ];
        }

        return $mapped;
    }

    private function sanitizeMediaUrl(mixed $url): ?string
    {
        if (!is_string($url)) {
            return null;
        }

        $normalized = trim($url);
        if ($normalized === '' || !filter_var($normalized, FILTER_VALIDATE_URL)) {
            return null;
        }

        $parts = parse_url($normalized);
        if (!is_array($parts)) {
            return null;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));
        if ($scheme !== 'https' || $host === '') {
            return null;
        }

        $allowedHosts = (array) config('services.giphy.allowed_media_hosts', []);
        if (!in_array($host, $allowedHosts, true)) {
            return null;
        }

        return $normalized;
    }

    /**
     * @param array<string,array<int,string>> $headers
     */
    private function logProviderRateHeaders(array $headers): void
    {
        $remaining = $headers['x-ratelimit-remaining'] ?? null;
        $reset = $headers['x-ratelimit-reset'] ?? null;
        $limit = $headers['x-ratelimit-limit'] ?? null;

        if ($remaining === null && $reset === null && $limit === null) {
            return;
        }

        Log::debug('GIPHY rate headers', [
            'remaining' => is_array($remaining) ? ($remaining[0] ?? null) : $remaining,
            'reset' => is_array($reset) ? ($reset[0] ?? null) : $reset,
            'limit' => is_array($limit) ? ($limit[0] ?? null) : $limit,
        ]);
    }
}
