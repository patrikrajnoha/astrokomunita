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
];

