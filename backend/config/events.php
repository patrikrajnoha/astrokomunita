<?php

return [
    'default_visibility' => 1,
    'source_timezone' => env('EVENTS_SOURCE_TIMEZONE', 'Europe/Bratislava'),
    'source_timezones' => [
        'default' => env('EVENTS_SOURCE_TIMEZONE', 'Europe/Bratislava'),
        'astropixels' => env('EVENTS_ASTROPIXELS_SOURCE_TIMEZONE', '+01:00'),
        'nasa' => 'UTC',
        'nasa_watch_the_skies' => 'UTC',
        'imo' => 'UTC',
    ],
    'crawler_ssl_verify' => filter_var(env('EVENTS_CRAWLER_SSL_VERIFY', true), FILTER_VALIDATE_BOOLEAN),
    'crawler_ssl_ca_bundle' => env('EVENTS_CRAWLER_CA_BUNDLE'),
    'timezone' => env('EVENTS_DISPLAY_TIMEZONE', 'Europe/Bratislava'),
    'description_template_min_length' => (int) env('EVENTS_DESCRIPTION_TEMPLATE_MIN_LENGTH', 40),
    'refine_descriptions_with_ollama' => filter_var(env('EVENTS_REFINE_DESCRIPTIONS_WITH_OLLAMA', false), FILTER_VALIDATE_BOOLEAN),
    'deduplication' => [
        'fuzzy' => [
            'enabled' => filter_var(env('EVENTS_DEDUP_FUZZY_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
            'window_hours' => (int) env('EVENTS_DEDUP_FUZZY_WINDOW_HOURS', 36),
            'min_title_similarity' => (float) env('EVENTS_DEDUP_FUZZY_MIN_TITLE_SIMILARITY', 0.86),
        ],
        'publish_fuzzy' => [
            'enabled' => filter_var(env('EVENTS_DEDUP_PUBLISH_FUZZY_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
            'window_hours' => (int) env('EVENTS_DEDUP_PUBLISH_FUZZY_WINDOW_HOURS', 36),
            'min_title_similarity' => (float) env('EVENTS_DEDUP_PUBLISH_FUZZY_MIN_TITLE_SIMILARITY', 0.86),
        ],
    ],
    'translation' => [
        'quality_gate' => [
            'force_template_on_severe_flags' => filter_var(
                env('EVENTS_TRANSLATION_FORCE_TEMPLATE_ON_SEVERE_FLAGS', true),
                FILTER_VALIDATE_BOOLEAN
            ),
            'severe_flags' => array_values(array_filter(array_map(
                static fn (string $flag): string => strtolower(trim($flag)),
                explode(
                    ',',
                    (string) env(
                        'EVENTS_TRANSLATION_SEVERE_FLAGS',
                        'empty_result,identical,too_short,too_much_en,contains_en_connectors,encoding_artifacts'
                    )
                )
            ))),
        ],
    ],
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
        'humanized_pilot_enabled' => filter_var(env('EVENTS_AI_HUMANIZED_PILOT_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
        'humanized_temperature' => (float) env('EVENTS_AI_HUMANIZED_TEMPERATURE', 0.3),
        'humanized_num_predict' => (int) env('EVENTS_AI_HUMANIZED_NUM_PREDICT', 520),
        'title_postedit_enabled' => filter_var(env('EVENTS_AI_TITLE_POSTEDIT_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
        'title_postedit_max_length' => (int) env('EVENTS_AI_TITLE_POSTEDIT_MAX_LENGTH', 120),
        'title_postedit_temperature' => (float) env('EVENTS_AI_TITLE_POSTEDIT_TEMPERATURE', 0.25),
        'title_postedit_num_predict' => (int) env('EVENTS_AI_TITLE_POSTEDIT_NUM_PREDICT', 120),
        'title_postedit_timeout' => (int) env('EVENTS_AI_TITLE_POSTEDIT_TIMEOUT', 25),
    ],
    'astropixels' => [
        'min_year' => (int) env('EVENTS_ASTROPIXELS_MIN_YEAR', 2021),
        'max_year' => (int) env('EVENTS_ASTROPIXELS_MAX_YEAR', 2100),
        'catalog_url' => env('EVENTS_ASTROPIXELS_CATALOG_URL', 'https://astropixels.com/almanac/almanac.html'),
        'catalog_cache_ttl_seconds' => (int) env('EVENTS_ASTROPIXELS_CATALOG_CACHE_TTL_SECONDS', 21600),
        'base_url_pattern' => env(
            'EVENTS_ASTROPIXELS_BASE_URL_PATTERN',
            'https://astropixels.com/almanac/almanac%2$02d/almanac%1$dcet.html'
        ),
    ],
    'nasa' => [
        'eclipses_year_url' => env('EVENTS_NASA_ECLIPSES_YEAR_URL', 'https://aa.usno.navy.mil/api/eclipses/solar/year'),
        'eclipse_date_url' => env('EVENTS_NASA_ECLIPSE_DATE_URL', 'https://aa.usno.navy.mil/api/eclipses/solar/date'),
        'location' => [
            'label' => env('EVENTS_NASA_LOCATION_LABEL', 'Bratislava, SK'),
            'lat' => (float) env('EVENTS_NASA_LOCATION_LAT', 48.1486),
            'lon' => (float) env('EVENTS_NASA_LOCATION_LON', 17.1077),
            'height_m' => (int) env('EVENTS_NASA_LOCATION_HEIGHT_M', 150),
        ],
        'include_only_visible' => filter_var(env('EVENTS_NASA_INCLUDE_ONLY_VISIBLE', true), FILTER_VALIDATE_BOOLEAN),
    ],
    'nasa_watch_the_skies' => [
        'moon_phases_year_url' => env('EVENTS_NASA_WTS_MOON_PHASES_YEAR_URL', 'https://aa.usno.navy.mil/api/moon/phases/year'),
        'url' => env('EVENTS_NASA_WTS_URL', env('EVENTS_NASA_WTS_MOON_PHASES_YEAR_URL', 'https://aa.usno.navy.mil/api/moon/phases/year')),
        'location_label' => env('EVENTS_NASA_WTS_LOCATION_LABEL', env('EVENTS_NASA_LOCATION_LABEL', 'Bratislava, SK')),
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
        'aurora',
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
