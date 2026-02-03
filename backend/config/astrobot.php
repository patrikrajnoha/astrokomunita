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
];
