<?php

return [
    'default_visibility' => 1,
    'source_timezone' => env('EVENTS_SOURCE_TIMEZONE', 'Europe/Bratislava'),
    'source_timezones' => [
        'default' => env('EVENTS_SOURCE_TIMEZONE', 'Europe/Bratislava'),
        'astropixels' => env('EVENTS_ASTROPIXELS_SOURCE_TIMEZONE', '+01:00'),
        'imo' => 'UTC',
    ],
    'crawler_ssl_verify' => filter_var(env('EVENTS_CRAWLER_SSL_VERIFY', true), FILTER_VALIDATE_BOOLEAN),
    'crawler_ssl_ca_bundle' => env('EVENTS_CRAWLER_CA_BUNDLE'),
    'timezone' => env('EVENTS_DISPLAY_TIMEZONE', 'Europe/Bratislava'),
    'description_template_min_length' => (int) env('EVENTS_DESCRIPTION_TEMPLATE_MIN_LENGTH', 40),
    'refine_descriptions_with_ollama' => filter_var(env('EVENTS_REFINE_DESCRIPTIONS_WITH_OLLAMA', false), FILTER_VALIDATE_BOOLEAN),
    'ai' => [
        'description_mode' => env('EVENTS_AI_DESCRIPTION_MODE', 'template'),
        'model' => env('EVENTS_AI_MODEL', config('ai.ollama.model', 'mistral')),
        'temperature' => (float) env('EVENTS_AI_TEMPERATURE', 0.2),
        'num_predict' => (int) env('EVENTS_AI_NUM_PREDICT', 420),
        'timeout' => (int) env('EVENTS_AI_TIMEOUT', 45),
        'retry_backoff_base_ms' => (int) env('EVENTS_AI_RETRY_BACKOFF_BASE_MS', 250),
        'insights_cache_ttl_seconds' => (int) env('EVENTS_AI_INSIGHTS_CACHE_TTL_SECONDS', 2_592_000),
        'last_run_cache_ttl_seconds' => (int) env(
            'EVENTS_AI_LAST_RUN_CACHE_TTL_SECONDS',
            (int) env('EVENTS_AI_INSIGHTS_CACHE_TTL_SECONDS', 2_592_000)
        ),
        'prime_insights_default_limit' => (int) env('EVENTS_AI_PRIME_INSIGHTS_DEFAULT_LIMIT', 5),
        'prime_insights_max_limit' => (int) env('EVENTS_AI_PRIME_INSIGHTS_MAX_LIMIT', 10),
        'prime_insights_lock_ttl_seconds' => (int) env('EVENTS_AI_PRIME_INSIGHTS_LOCK_TTL_SECONDS', 60),
        'humanized_pilot_enabled' => filter_var(env('EVENTS_AI_HUMANIZED_PILOT_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
        'humanized_temperature' => (float) env('EVENTS_AI_HUMANIZED_TEMPERATURE', 0.3),
        'humanized_num_predict' => (int) env('EVENTS_AI_HUMANIZED_NUM_PREDICT', 520),
        'title_postedit_admin_enabled' => filter_var(env('EVENTS_AI_TITLE_POSTEDIT_ADMIN_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
        'title_postedit_enabled' => filter_var(env('EVENTS_AI_TITLE_POSTEDIT_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
        'title_postedit_max_length' => (int) env('EVENTS_AI_TITLE_POSTEDIT_MAX_LENGTH', 120),
        'title_postedit_temperature' => (float) env('EVENTS_AI_TITLE_POSTEDIT_TEMPERATURE', 0.25),
        'title_postedit_num_predict' => (int) env('EVENTS_AI_TITLE_POSTEDIT_NUM_PREDICT', 120),
        'title_postedit_timeout' => (int) env('EVENTS_AI_TITLE_POSTEDIT_TIMEOUT', 25),
        'newsletter_copy_draft_admin_enabled' => filter_var(env('EVENTS_AI_NEWSLETTER_COPY_DRAFT_ADMIN_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
    ],
    'astropixels' => [
        'min_year' => 2021,
        'max_year' => 2030,
        'base_url_pattern' => 'https://astropixels.com/almanac/almanac21/almanac%dcet.html',
    ],
    'imo' => [
        'url' => env('EVENTS_IMO_URL', 'https://www.imo.net/resources/calendar/'),
    ],

    'types' => [
        'meteors',
        'meteor_shower',
        'eclipse',
        'eclipse_lunar',
        'eclipse_solar',
        'conjunction',
        'planetary_event',
        'comet',
        'asteroid',
        'space_event',
        'observation_window',
        'planet',
        'mission',
        'other',
    ],

    'regions' => [
        'sk',
        'eu',
        'global',
    ],

    'defaults' => [
        'region' => 'global',
        'event_types' => [],
    ],

    'public_confidence' => [
        'verified_score' => (int) env('EVENTS_CONFIDENCE_VERIFIED_SCORE', 80),
        'partial_score' => (int) env('EVENTS_CONFIDENCE_PARTIAL_SCORE', 60),
        'verified_min_sources' => (int) env('EVENTS_CONFIDENCE_VERIFIED_MIN_SOURCES', 2),
        'partial_min_sources' => (int) env('EVENTS_CONFIDENCE_PARTIAL_MIN_SOURCES', 1),
    ],
];
