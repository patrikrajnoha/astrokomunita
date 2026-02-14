<?php

return [
    /*
    |--------------------------------------------------------------------------
    | AstroBot Autonomous NASA RSS Mode
    |--------------------------------------------------------------------------
    */
    'enabled' => env('ASTROBOT_ENABLED', true),
    'nasa_rss_url' => env('ASTROBOT_NASA_RSS_URL', 'https://www.nasa.gov/news-release/feed/'),
    'keep_max_items' => (int) env('ASTROBOT_KEEP_MAX_ITEMS', 30),
    'keep_max_days' => (int) env('ASTROBOT_KEEP_MAX_DAYS', 14),
    'lock_ttl_seconds' => (int) env('ASTROBOT_LOCK_TTL_SECONDS', 3300),

    /*
    |--------------------------------------------------------------------------
    | RSS HTTP
    |--------------------------------------------------------------------------
    */
    'rss_timeout_seconds' => (int) env('ASTROBOT_RSS_TIMEOUT_SECONDS', 10),
    'rss_retry_times' => (int) env('ASTROBOT_RSS_RETRY_TIMES', 2),
    'rss_retry_sleep_ms' => (int) env('ASTROBOT_RSS_RETRY_SLEEP_MS', 250),
    'rss_user_agent' => env('ASTROBOT_RSS_USER_AGENT', 'AstroKomunita/AstroBot RSS Sync'),
    'max_items_per_sync' => (int) env('ASTROBOT_MAX_ITEMS_PER_SYNC', 100),
    'ssl_verify' => filter_var(env('ASTROBOT_SSL_VERIFY', true), FILTER_VALIDATE_BOOL),
    'ssl_ca_bundle' => env('ASTROBOT_SSL_CA_BUNDLE'),

    /*
    |--------------------------------------------------------------------------
    | Legacy keys kept for backward compatibility
    |--------------------------------------------------------------------------
    */
    'post_ttl_hours' => (int) env('ASTROBOT_POST_TTL_HOURS', 24),
    'purge_permanently' => env('ASTROBOT_PURGE_PERMANENTLY', true),
    'cleanup_frequency' => env('ASTROBOT_CLEANUP_FREQUENCY', 'hourly'),
    'debug_logging' => env('ASTROBOT_DEBUG_LOGGING', env('APP_ENV') === 'local'),
    'max_posts_per_cleanup' => (int) env('ASTROBOT_MAX_POSTS_PER_CLEANUP', 100),
    'rss_retention_days' => (int) env('ASTROBOT_RSS_RETENTION_DAYS', 30),
    'rss_retention_max_items' => (int) env('ASTROBOT_RSS_RETENTION_MAX_ITEMS', 200),
    'rss_url' => env('ASTROBOT_RSS_URL', env('ASTROBOT_NASA_RSS_URL', 'https://www.nasa.gov/news-release/feed/')),
    'rss_max_items' => (int) env('ASTROBOT_RSS_MAX_ITEMS', 100),
    'rss_max_payload_kb' => (int) env('ASTROBOT_RSS_MAX_PAYLOAD_KB', 1024),
    'max_age_days' => (int) env('ASTROBOT_RSS_MAX_AGE_DAYS', 30),
    'auto_publish_enabled' => env('ASTROBOT_AUTO_PUBLISH_ENABLED', true),
    'domain_whitelist' => array_values(array_filter(array_map(
        static fn (string $host): string => strtolower(trim($host)),
        explode(',', (string) env('ASTROBOT_DOMAIN_WHITELIST', ''))
    ))),
    'risk_keywords' => array_values(array_filter(array_map(
        static fn (string $keyword): string => strtolower(trim($keyword)),
        explode(',', (string) env('ASTROBOT_RISK_KEYWORDS', '!!!,crypto,free,win'))
    ))),
];
