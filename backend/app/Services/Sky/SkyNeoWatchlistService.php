<?php

namespace App\Services\Sky;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

class SkyNeoWatchlistService
{
    private const CACHE_KEY = 'sky_neo_watchlist:v2';
    private const LAST_KNOWN_CACHE_KEY = 'sky_neo_watchlist:last_known:v2';
    private const SOURCE_URL = 'https://ssd-api.jpl.nasa.gov/doc/sbdb_query.html';

    public function __construct(
        private readonly SkyEphemerisService $skyEphemerisService,
    ) {
    }

    /**
     * @return array<string,mixed>
     */
    public function fetch(): array
    {
        $cached = Cache::get(self::CACHE_KEY);
        if (is_array($cached)) {
            return $cached;
        }

        $payload = $this->buildPayload();
        $lastKnownPayload = Cache::get(self::LAST_KNOWN_CACHE_KEY);
        $ttlMinutes = max(
            1,
            (int) config(
                'widgets.neo_watchlist.cache_ttl_minutes',
                (int) config('observing.sky.ephemeris_cache_ttl_minutes', 30)
            )
        );

        if (!empty($payload['available'])) {
            $ttl = now()->addMinutes($ttlMinutes);
            Cache::put(self::CACHE_KEY, $payload, $ttl);
            Cache::put(
                self::LAST_KNOWN_CACHE_KEY,
                $payload,
                now()->addMinutes(max(
                    $ttlMinutes,
                    (int) config('widgets.neo_watchlist.last_known_ttl_minutes', 720)
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
            Cache::put(self::CACHE_KEY, $fallbackPayload, now()->addMinutes(5));

            return $fallbackPayload;
        }

        Cache::put(self::CACHE_KEY, $payload, now()->addMinutes(5));

        return $payload;
    }

    /**
     * @return array<string,mixed>
     */
    private function buildPayload(): array
    {
        $updatedAt = CarbonImmutable::now('UTC')->toIso8601String();
        $items = $this->skyEphemerisService->fetchNeoWatchlist(5);

        return [
            'available' => $items !== [],
            'items' => $items,
            'source' => [
                'provider' => 'jpl_sbddb',
                'label' => 'NASA JPL SBDB',
                'url' => self::SOURCE_URL,
                'api_key_required' => false,
            ],
            'updated_at' => $updatedAt,
            ...($items === [] ? ['reason' => 'provider_unavailable'] : []),
        ];
    }

    private function hasAvailableItems(mixed $payload): bool
    {
        if (!is_array($payload)) {
            return false;
        }

        if (!($payload['available'] ?? false)) {
            return false;
        }

        return is_array($payload['items'] ?? null) && $payload['items'] !== [];
    }
}
