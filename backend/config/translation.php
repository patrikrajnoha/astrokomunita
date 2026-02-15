<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Translation Provider
    |--------------------------------------------------------------------------
    |
    | Primary provider is LibreTranslate (self-hosted recommended). A fallback
    | provider can point to the existing Argos microservice for compatibility.
    |
    */
    'default_provider' => env('TRANSLATION_DEFAULT_PROVIDER', 'libretranslate'),
    'fallback_provider' => env('TRANSLATION_FALLBACK_PROVIDER', 'argos_microservice'),

    /*
    |--------------------------------------------------------------------------
    | Cache
    |--------------------------------------------------------------------------
    */
    'cache_enabled' => filter_var(env('TRANSLATION_CACHE_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
    'cache_ttl' => (int) env('TRANSLATION_CACHE_TTL', 86400),
    'cache_key_version' => (string) env('TRANSLATION_CACHE_KEY_VERSION', 'v7'),

    /*
    |--------------------------------------------------------------------------
    | Queue behavior
    |--------------------------------------------------------------------------
    |
    | Translation jobs are intended to be async. When queue connection is
    | "sync", event-candidate translation dispatch can be skipped to avoid
    | blocking crawl/import execution paths.
    |
    */
    'allow_sync_queue' => filter_var(env('TRANSLATION_ALLOW_SYNC_QUEUE', false), FILTER_VALIDATE_BOOLEAN),
    'events' => [
        'enabled' => filter_var(env('TRANSLATION_EVENTS_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
    ],

    /*
    |--------------------------------------------------------------------------
    | Grammar quality pass (optional, fail-open)
    |--------------------------------------------------------------------------
    |
    | Runs after translation to improve Slovak grammar quality.
    | Recommended provider: self-hosted LanguageTool.
    |
    */
    'grammar' => [
        'enabled' => filter_var(env('TRANSLATION_GRAMMAR_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
        'provider' => env('TRANSLATION_GRAMMAR_PROVIDER', 'languagetool'),
        'languages' => array_values(array_filter(array_map(
            static fn (string $value): string => trim($value),
            explode(',', (string) env('TRANSLATION_GRAMMAR_LANGUAGES', 'sk'))
        ))),

        'languagetool' => [
            'base_url' => env('LANGUAGETOOL_BASE_URL', 'http://127.0.0.1:8081'),
            'check_path' => env('LANGUAGETOOL_CHECK_PATH', '/v2/check'),
            'language' => env('LANGUAGETOOL_LANGUAGE', 'sk-SK'),
            'timeout' => (int) env('LANGUAGETOOL_TIMEOUT', 6),
            'connect_timeout' => (int) env('LANGUAGETOOL_CONNECT_TIMEOUT', 2),
            'retry' => (int) env('LANGUAGETOOL_RETRY', 1),
            'retry_sleep_ms' => (int) env('LANGUAGETOOL_RETRY_SLEEP_MS', 200),
            'verify_ssl' => filter_var(env('LANGUAGETOOL_VERIFY_SSL', true), FILTER_VALIDATE_BOOLEAN),
            'internal_token' => env('LANGUAGETOOL_INTERNAL_TOKEN', env('TRANSLATION_INTERNAL_TOKEN', env('INTERNAL_TOKEN', ''))),
            'max_fixes' => (int) env('LANGUAGETOOL_MAX_FIXES', 30),
            'enabled_rules' => env('LANGUAGETOOL_ENABLED_RULES', ''),
            'disabled_rules' => env('LANGUAGETOOL_DISABLED_RULES', ''),
            'enabled_only' => filter_var(env('LANGUAGETOOL_ENABLED_ONLY', false), FILTER_VALIDATE_BOOLEAN),
        ],
    ],

    'libretranslate' => [
        'base_url' => env('LIBRETRANSLATE_BASE_URL', 'http://127.0.0.1:5000'),
        'translate_path' => env('LIBRETRANSLATE_TRANSLATE_PATH', '/translate'),
        'timeout' => (int) env('LIBRETRANSLATE_TIMEOUT', 12),
        'connect_timeout' => (int) env('LIBRETRANSLATE_CONNECT_TIMEOUT', 3),
        'retry' => (int) env('LIBRETRANSLATE_RETRY', 2),
        'retry_sleep_ms' => (int) env('LIBRETRANSLATE_RETRY_SLEEP_MS', 250),
        'internal_token' => env('LIBRETRANSLATE_INTERNAL_TOKEN', env('TRANSLATION_INTERNAL_TOKEN', env('INTERNAL_TOKEN', ''))),
        'api_key' => env('LIBRETRANSLATE_API_KEY'),
        'verify_ssl' => filter_var(env('LIBRETRANSLATE_VERIFY_SSL', true), FILTER_VALIDATE_BOOLEAN),
    ],

    /*
    |--------------------------------------------------------------------------
    | Legacy Argos Microservice (fallback)
    |--------------------------------------------------------------------------
    |
    | Backward-compatible fallback to existing /translate endpoint currently
    | used in project Python microservice (`services/sky`).
    |
    */
    'argos_microservice' => [
        'base_url' => env('TRANSLATION_SERVICE_URL', env('OBSERVING_SKY_MICROSERVICE_BASE', 'http://127.0.0.1:8010')),
        'translate_path' => env('TRANSLATION_SERVICE_TRANSLATE_PATH', '/translate'),
        'diagnostics_path' => env('TRANSLATION_SERVICE_DIAGNOSTICS_PATH', '/diagnostics'),
        'timeout' => (int) env('TRANSLATION_TIMEOUT_SECONDS', 12),
        'connect_timeout' => (int) env('TRANSLATION_CONNECT_TIMEOUT_SECONDS', 3),
        'retry' => (int) env('TRANSLATION_RETRIES', 2),
        'retry_sleep_ms' => (int) env('TRANSLATION_RETRY_SLEEP_MS', 250),
        'internal_token' => env('TRANSLATION_INTERNAL_TOKEN', env('INTERNAL_TOKEN', '')),
        'default_domain' => env('TRANSLATION_DEFAULT_DOMAIN', 'astronomy'),
    ],
];
