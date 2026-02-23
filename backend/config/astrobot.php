<?php

return [
    /*
    |--------------------------------------------------------------------------
    | AstroBot Autonomous NASA RSS Mode
    |--------------------------------------------------------------------------
    */
    'enabled' => env('ASTROBOT_ENABLED', true),
    'nasa_rss_url' => env('ASTROBOT_NASA_RSS_URL', 'https://www.nasa.gov/news-release/feed/'),
    'nasa_apod_url' => env('NASA_APOD_URL', env('ASTROBOT_NASA_APOD_URL', 'https://api.nasa.gov/planetary/apod')),
    'wikipedia_onthisday_url' => env('WIKIPEDIA_ONTHISDAY_URL', 'https://api.wikimedia.org/feed/v1/wikipedia/en/onthisday/all'),
    'sources' => [
        'nasa_rss_breaking' => [
            'label' => env('BOT_SOURCE_NASA_RSS_LABEL', 'NASA RSS'),
            'attribution' => env('BOT_SOURCE_NASA_RSS_ATTRIBUTION', 'NASA'),
            'default_mode' => env('BOT_SOURCE_NASA_RSS_DEFAULT_MODE', 'auto'),
        ],
        'nasa_apod_daily' => [
            'label' => env('BOT_SOURCE_NASA_APOD_LABEL', 'NASA APOD'),
            'attribution' => env('BOT_SOURCE_NASA_APOD_ATTRIBUTION', 'NASA'),
            'default_mode' => env('BOT_SOURCE_NASA_APOD_DEFAULT_MODE', 'auto'),
        ],
        'wiki_onthisday_astronomy' => [
            'label' => env('BOT_SOURCE_WIKI_ONTHISDAY_LABEL', 'Wikipedia On This Day'),
            'attribution' => env('BOT_SOURCE_WIKI_ONTHISDAY_ATTRIBUTION', 'Wikipedia'),
            'default_mode' => env('BOT_SOURCE_WIKI_ONTHISDAY_DEFAULT_MODE', 'auto'),
        ],
    ],
    'keep_max_items' => (int) env('ASTROBOT_KEEP_MAX_ITEMS', 30),
    'keep_max_days' => (int) env('ASTROBOT_KEEP_MAX_DAYS', 14),
    'lock_ttl_seconds' => (int) env('ASTROBOT_LOCK_TTL_SECONDS', 3300),
    'run_lock_ttl_seconds' => (int) env('ASTROBOT_RUN_LOCK_TTL_SECONDS', 600),

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
    | Translation
    |--------------------------------------------------------------------------
    */
    'translation' => [
        'target_lang' => strtolower(trim((string) env('BOT_TRANSLATION_TARGET_LANG', 'sk'))),
        'source_lang' => strtolower(trim((string) env('BOT_TRANSLATION_SOURCE_LANG', 'en'))),
        // Supported providers: libretranslate, ollama (legacy aliases: http, dummy)
        'primary' => strtolower(trim((string) env('BOT_TRANSLATION_PRIMARY', env('TRANSLATION_PROVIDER', 'libretranslate')))),
        'fallback' => strtolower(trim((string) env('BOT_TRANSLATION_FALLBACK', 'ollama'))),
        'chunk_max_chars' => (int) env('BOT_TRANSLATION_CHUNK_MAX_CHARS', 1800),
        'chunk_hard_limit_chars' => (int) env('BOT_TRANSLATION_CHUNK_HARD_LIMIT_CHARS', 3500),
        'libretranslate' => [
            'url' => env('BOT_TRANSLATION_LIBRETRANSLATE_URL', env('TRANSLATION_BASE_URL', 'http://127.0.0.1:5000')),
            'timeout_seconds' => (int) env('BOT_TRANSLATION_LIBRETRANSLATE_TIMEOUT_SECONDS', env('TRANSLATION_TIMEOUT_SECONDS', 8)),
            'retry_times' => (int) env('BOT_TRANSLATION_LIBRETRANSLATE_RETRY_TIMES', 1),
            'retry_sleep_ms' => (int) env('BOT_TRANSLATION_LIBRETRANSLATE_RETRY_SLEEP_MS', 200),
            'api_key' => env('BOT_TRANSLATION_LIBRETRANSLATE_API_KEY'),
        ],
        'ollama' => [
            'model' => env('BOT_TRANSLATION_OLLAMA_MODEL', env('TRANSLATION_OLLAMA_MODEL', env('OLLAMA_MODEL', 'mistral'))),
            'timeout_seconds' => (int) env('BOT_TRANSLATION_OLLAMA_TIMEOUT_SECONDS', env('TRANSLATION_OLLAMA_TIMEOUT_SECONDS', env('OLLAMA_TIMEOUT', 40))),
            'temperature' => (float) env('BOT_TRANSLATION_OLLAMA_TEMPERATURE', env('TRANSLATION_OLLAMA_TEMPERATURE', 0.0)),
            'num_predict' => (int) env('BOT_TRANSLATION_OLLAMA_NUM_PREDICT', env('TRANSLATION_OLLAMA_NUM_PREDICT', 700)),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Translation (legacy flat keys; kept for compatibility)
    |--------------------------------------------------------------------------
    */
    'translation_provider' => strtolower(trim((string) env('TRANSLATION_PROVIDER', env('BOT_TRANSLATION_PRIMARY', 'libretranslate')))),
    'translation_fallback_provider' => strtolower(trim((string) env('TRANSLATION_FALLBACK_PROVIDER', ''))),
    'translation_base_url' => env('TRANSLATION_BASE_URL', env('BOT_TRANSLATION_LIBRETRANSLATE_URL', 'http://127.0.0.1:5000')),
    'translation_timeout_seconds' => (int) env('TRANSLATION_TIMEOUT_SECONDS', env('BOT_TRANSLATION_LIBRETRANSLATE_TIMEOUT_SECONDS', 8)),
    'translation_ollama_model' => env('TRANSLATION_OLLAMA_MODEL', env('BOT_TRANSLATION_OLLAMA_MODEL', env('OLLAMA_MODEL', 'mistral'))),
    'translation_ollama_timeout_seconds' => (int) env('TRANSLATION_OLLAMA_TIMEOUT_SECONDS', env('BOT_TRANSLATION_OLLAMA_TIMEOUT_SECONDS', env('OLLAMA_TIMEOUT', 40))),
    'translation_ollama_temperature' => (float) env('TRANSLATION_OLLAMA_TEMPERATURE', env('BOT_TRANSLATION_OLLAMA_TEMPERATURE', 0.0)),
    'translation_ollama_num_predict' => (int) env('TRANSLATION_OLLAMA_NUM_PREDICT', env('BOT_TRANSLATION_OLLAMA_NUM_PREDICT', 700)),

    /*
    |--------------------------------------------------------------------------
    | Wikipedia + Wikidata Classification
    |--------------------------------------------------------------------------
    */
    'wikipedia_mediawiki_api_url' => env('WIKIPEDIA_MEDIAWIKI_API_URL', 'https://en.wikipedia.org/w/api.php'),
    'wikidata_api_url' => env('WIKIDATA_API_URL', 'https://www.wikidata.org/w/api.php'),
    'wiki_max_candidate_pages' => (int) env('WIKI_MAX_CANDIDATE_PAGES', 15),
    'wiki_max_wikidata_entity_requests' => (int) env('WIKI_MAX_WIKIDATA_ENTITY_REQUESTS', 15),
    'wiki_wikidata_cache_ttl_days' => (int) env('WIKI_WIKIDATA_CACHE_TTL_DAYS', 30),
    'wiki_high_keyword_threshold' => (int) env('WIKI_HIGH_KEYWORD_THRESHOLD', 4),
    'wiki_allowlist_qids' => array_values(array_filter(array_map(
        static fn (string $qid): string => strtoupper(trim($qid)),
        explode(',', (string) env('WIKI_ALLOWLIST_QIDS', ''))
    ))),
    'wiki_denylist_qids' => array_values(array_filter(array_map(
        static fn (string $qid): string => strtoupper(trim($qid)),
        explode(',', (string) env('WIKI_DENYLIST_QIDS', ''))
    ))),

    /*
    |--------------------------------------------------------------------------
    | Stela image download policy
    |--------------------------------------------------------------------------
    */
    'stela_image_max_bytes' => (int) env('STELA_IMAGE_MAX_BYTES', 20 * 1024 * 1024),
    'stela_image_allowed_mimes' => array_values(array_filter(array_map(
        static fn (string $mime): string => strtolower(trim($mime)),
        explode(',', (string) env('STELA_IMAGE_ALLOWED_MIMES', 'image/jpeg,image/png,image/webp'))
    ))),

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
