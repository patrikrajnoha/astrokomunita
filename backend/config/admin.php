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
    'stats_ip_region_enabled' => (bool) env('ADMIN_STATS_IP_REGION_ENABLED', true),
    'stats_ip_region_cache_ttl_seconds' => (int) env('ADMIN_STATS_IP_REGION_CACHE_TTL_SECONDS', 86400),
    'stats_ip_region_lookup_max_per_build' => (int) env('ADMIN_STATS_IP_REGION_LOOKUP_MAX_PER_BUILD', 64),
    'ai_rate_limit_per_minute' => (int) env('ADMIN_AI_RATE_LIMIT_PER_MINUTE', 10),

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
