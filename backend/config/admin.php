<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Admin Stats Cache TTL
    |--------------------------------------------------------------------------
    |
    | Cache lifetime in seconds for /api/admin/stats payload.
    |
    */
    'stats_cache_ttl_seconds' => (int) env('ADMIN_STATS_CACHE_TTL_SECONDS', 60),

    /*
    |--------------------------------------------------------------------------
    | Performance Benchmark - Allowed Bot Sources
    |--------------------------------------------------------------------------
    */
    'performance' => [
        'allowed_bot_sources' => array_values(array_filter(array_map(
            static fn ($value): string => strtolower(trim((string) $value)),
            explode(',', (string) env('ADMIN_PERF_ALLOWED_BOT_SOURCES', 'nasa_rss_breaking'))
        ), static fn (string $value): bool => $value !== '')),
    ],
];
