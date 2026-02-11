<?php

return [
    /*
    |--------------------------------------------------------------------------
    | AstroBot Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration settings for AstroBot automated posting and cleanup.
    |
    */

    /**
     * How many hours after publishing should AstroBot posts be purged.
     * Default: 24 hours
     */
    'post_ttl_hours' => env('ASTROBOT_POST_TTL_HOURS', 24),

    /**
     * Whether to permanently delete old posts or just hide them.
     * true = permanent deletion, false = mark as hidden
     * Default: true (permanent deletion)
     */
    'purge_permanently' => env('ASTROBOT_PURGE_PERMANENTLY', true),

    /**
     * Schedule frequency for cleanup operations.
     * Available: everyMinute, hourly, everyTwoHours, everySixHours, daily
     * Default: hourly
     */
    'cleanup_frequency' => env('ASTROBOT_CLEANUP_FREQUENCY', 'hourly'),

    /**
     * Enable debug logging for AstroBot operations.
     * Default: false in production, true in local development
     */
    'debug_logging' => env('ASTROBOT_DEBUG_LOGGING', env('APP_ENV') === 'local'),

    /**
     * Maximum number of posts to process in a single cleanup run.
     * This prevents timeout issues with large datasets.
     * Default: 100
     */
    'max_posts_per_cleanup' => env('ASTROBOT_MAX_POSTS_PER_CLEANUP', 100),

    /**
     * Keep non-published RSS items for this many days.
     * Set to 0 to disable age-based cleanup.
     */
    'rss_retention_days' => env('ASTROBOT_RSS_RETENTION_DAYS', 30),

    /**
     * Keep at most this many non-published RSS items.
     * Set to 0 to disable count-based cleanup.
     */
    'rss_retention_max_items' => env('ASTROBOT_RSS_RETENTION_MAX_ITEMS', 200),

    /**
     * AstroBot RSS synchronization source URL.
     */
    'rss_url' => env('ASTROBOT_RSS_URL', 'https://www.nasa.gov/news-release/feed/'),

    /**
     * Timeout for RSS requests in seconds.
     */
    'rss_timeout_seconds' => env('ASTROBOT_RSS_TIMEOUT_SECONDS', 10),

    /**
     * Number of retry attempts for RSS HTTP requests.
     */
    'rss_retry_times' => env('ASTROBOT_RSS_RETRY_TIMES', 2),

    /**
     * Delay between retries in milliseconds.
     */
    'rss_retry_sleep_ms' => env('ASTROBOT_RSS_RETRY_SLEEP_MS', 250),

    /**
     * User-Agent used for RSS synchronization.
     */
    'rss_user_agent' => env('ASTROBOT_RSS_USER_AGENT', 'AstroKomunita/AstroBot RSS Sync'),

    /**
     * Maximum number of feed items processed in a single run.
     */
    'rss_max_items' => env('ASTROBOT_RSS_MAX_ITEMS', 100),

    /**
     * Preferred key for max item processing count per sync.
     */
    'max_items_per_sync' => env('ASTROBOT_MAX_ITEMS_PER_SYNC', env('ASTROBOT_RSS_MAX_ITEMS', 100)),

    /**
     * Maximum RSS payload size in kilobytes.
     */
    'rss_max_payload_kb' => env('ASTROBOT_RSS_MAX_PAYLOAD_KB', 1024),

    /**
     * Safety cleanup: remove stale non-published items older than this amount of days.
     */
    'max_age_days' => env('ASTROBOT_RSS_MAX_AGE_DAYS', 30),

    /**
     * Default behavior: publish safe items automatically.
     */
    'auto_publish_enabled' => env('ASTROBOT_AUTO_PUBLISH_ENABLED', true),

    /**
     * Optional trusted domains list. Empty array means no whitelist enforcement.
     *
     * Example .env value:
     * ASTROBOT_DOMAIN_WHITELIST=nasa.gov,www.nasa.gov
     */
    'domain_whitelist' => array_values(array_filter(array_map(
        static fn (string $host): string => strtolower(trim($host)),
        explode(',', (string) env('ASTROBOT_DOMAIN_WHITELIST', ''))
    ))),

    /**
     * Keyword-based risk filter for titles/summaries.
     */
    'risk_keywords' => array_values(array_filter(array_map(
        static fn (string $keyword): string => strtolower(trim($keyword)),
        explode(',', (string) env('ASTROBOT_RISK_KEYWORDS', '!!!,crypto,free,win'))
    ))),
];
