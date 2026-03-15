<?php

namespace App\Services\Sky;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

class SkyNeoWatchlistService
{
    private const CACHE_KEY = 'sky_neo_watchlist:v1';
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
        $ttlMinutes = max(
            1,
            (int) config(
                'widgets.neo_watchlist.cache_ttl_minutes',
                (int) config('observing.sky.ephemeris_cache_ttl_minutes', 30)
            )
        );
        $ttl = !empty($payload['available'])
            ? now()->addMinutes($ttlMinutes)
            : now()->addMinutes(5);

        Cache::put(self::CACHE_KEY, $payload, $ttl);

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
        ];
    }
}
