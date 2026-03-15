<?php

namespace App\Services\Sky;

use App\Support\Http\SslVerificationPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SkyUpcomingLaunchesService
{
    private const CACHE_KEY_PREFIX = 'sky_upcoming_launches:v1';
    private const LAST_KNOWN_CACHE_KEY_PREFIX = 'sky_upcoming_launches:last_known:v1';
    private const SOURCE_URL = 'https://thespacedevs.com/llapi';

    public function __construct(
        private readonly HttpFactory $http,
        private readonly SslVerificationPolicy $sslVerificationPolicy,
    ) {
    }

    /**
     * @return array<string,mixed>
     */
    public function fetch(int $limit = 3): array
    {
        $resolvedLimit = max(1, min(5, $limit));
        $cacheKey = $this->cacheKey($resolvedLimit);
        $lastKnownCacheKey = $this->lastKnownCacheKey($resolvedLimit);
        $cached = Cache::get($cacheKey);

        if (is_array($cached)) {
            return $cached;
        }

        $payload = $this->buildPayload($resolvedLimit);
        $lastKnownPayload = Cache::get($lastKnownCacheKey);
        $ttlMinutes = max(1, (int) config('widgets.upcoming_launches.cache_ttl_minutes', 15));

        if ($this->hasAvailableItems($payload)) {
            Cache::put($cacheKey, $payload, now()->addMinutes($ttlMinutes));
            Cache::put(
                $lastKnownCacheKey,
                $payload,
                now()->addMinutes(max(
                    $ttlMinutes,
                    (int) config('widgets.upcoming_launches.last_known_ttl_minutes', 360)
                ))
            );

            return $payload;
        }

        if ($this->hasAvailableItems($lastKnownPayload)) {
            $fallbackPayload = [
                ...$lastKnownPayload,
                'stale' => true,
                'refresh_attempted_at' => $payload['updated_at'] ?? CarbonImmutable::now('UTC')->toIso8601String(),
            ];
            Cache::put($cacheKey, $fallbackPayload, now()->addMinutes(5));

            return $fallbackPayload;
        }

        Cache::put($cacheKey, $payload, now()->addMinutes(5));

        return $payload;
    }

    /**
     * @return array<string,mixed>
     */
    private function buildPayload(int $limit): array
    {
        $updatedAt = CarbonImmutable::now('UTC')->toIso8601String();
        $items = $this->fetchUpcomingLaunches($limit);

        return [
            'available' => $items !== [],
            'items' => $items,
            'source' => [
                'provider' => 'launch_library_2',
                'label' => 'The Space Devs Launch Library 2',
                'url' => self::SOURCE_URL,
                'api_key_required' => false,
            ],
            'updated_at' => $updatedAt,
            ...($items === [] ? ['reason' => 'provider_unavailable'] : []),
        ];
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function fetchUpcomingLaunches(int $limit): array
    {
        $providerUrl = trim((string) config('observing.providers.launch_library_upcoming_url', ''));
        if ($providerUrl === '') {
            return [];
        }

        try {
            $response = $this->jsonRequest()->get($providerUrl, [
                'format' => 'json',
                'mode' => 'list',
                'limit' => $limit,
                'hide_recent_previous' => 'true',
                'ordering' => 'net',
            ]);
        } catch (\Throwable $exception) {
            $this->logProviderFailure($providerUrl, $exception);
            return [];
        }

        if (! $response->successful()) {
            return [];
        }

        $payload = $response->json();
        $results = is_array($payload['results'] ?? null) ? $payload['results'] : [];
        if ($results === []) {
            return [];
        }

        $normalized = [];

        foreach ($results as $launch) {
            if (! is_array($launch)) {
                continue;
            }

            $name = $this->sanitizeText($launch['name'] ?? null);
            if ($name === null) {
                continue;
            }

            $normalized[] = [
                'id' => $this->sanitizeText($launch['id'] ?? null),
                'name' => $name,
                'slug' => $this->sanitizeText($launch['slug'] ?? null),
                'net' => $this->sanitizeDateTime($launch['net'] ?? null),
                'window_start' => $this->sanitizeDateTime($launch['window_start'] ?? null),
                'window_end' => $this->sanitizeDateTime($launch['window_end'] ?? null),
                'last_updated' => $this->sanitizeDateTime($launch['last_updated'] ?? null),
                'provider' => $this->sanitizeText($launch['lsp_name'] ?? null),
                'mission_name' => $this->sanitizeMission($launch['mission'] ?? null),
                'mission_type' => $this->sanitizeText($launch['mission_type'] ?? null),
                'pad' => $this->sanitizeText($launch['pad'] ?? null),
                'location' => $this->sanitizeText($launch['location'] ?? null),
                'status' => [
                    'label' => $this->sanitizeText(data_get($launch, 'status.name')),
                    'abbrev' => $this->sanitizeText(data_get($launch, 'status.abbrev')),
                    'description' => $this->sanitizeText(data_get($launch, 'status.description')),
                ],
            ];
        }

        usort($normalized, static function (array $left, array $right): int {
            return strcmp((string) ($left['net'] ?? ''), (string) ($right['net'] ?? ''));
        });

        return array_values(array_slice($normalized, 0, $limit));
    }

    private function cacheKey(int $limit): string
    {
        return self::CACHE_KEY_PREFIX.':'.$limit;
    }

    private function lastKnownCacheKey(int $limit): string
    {
        return self::LAST_KNOWN_CACHE_KEY_PREFIX.':'.$limit;
    }

    private function hasAvailableItems(mixed $payload): bool
    {
        if (! is_array($payload) || ! ($payload['available'] ?? false)) {
            return false;
        }

        return is_array($payload['items'] ?? null) && $payload['items'] !== [];
    }

    private function sanitizeMission(mixed $value): ?string
    {
        $mission = $this->sanitizeText($value);
        if ($mission === null) {
            return null;
        }

        return strcasecmp($mission, 'Unknown Payload') === 0 ? null : $mission;
    }

    private function sanitizeText(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed !== '' ? $trimmed : null;
    }

    private function sanitizeDateTime(mixed $value): ?string
    {
        $candidate = $this->sanitizeText($value);
        if ($candidate === null) {
            return null;
        }

        try {
            return CarbonImmutable::parse($candidate)->toIso8601String();
        } catch (\Throwable) {
            return null;
        }
    }

    private function jsonRequest(): PendingRequest
    {
        $request = $this->http
            ->timeout((int) config('observing.http.timeout_seconds', 8))
            ->retry(
                (int) config('observing.http.retry_times', 2),
                (int) config('observing.http.retry_sleep_ms', 200)
            )
            ->acceptJson();

        $verifyOption = $this->resolveSslVerifyOption();

        return $request
            ->withOptions(['verify' => $verifyOption])
            ->withAttributes(['ssl_verify' => $verifyOption]);
    }

    private function resolveSslVerifyOption(): bool|string
    {
        $caBundlePath = trim((string) config('observing.http.local_ca_bundle_path', ''));
        if (app()->environment('local') && $caBundlePath !== '' && is_file($caBundlePath)) {
            return $caBundlePath;
        }

        return $this->sslVerificationPolicy->resolveVerifyOption();
    }

    private function logProviderFailure(string $url, \Throwable $exception): void
    {
        Log::warning('Upcoming launches provider request failed.', [
            'provider' => 'launch_library_2',
            'url' => $url,
            'exception_class' => $exception::class,
            'exception_message' => $exception->getMessage(),
        ]);
    }
}
