<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'openaq' => [
        'key' => env('OPENAQ_API_KEY'),
        'base_url' => env('OPENAQ_BASE_URL', 'https://api.openaq.org/v3'),
    ],

    'translation' => [
        'base_url' => env('TRANSLATION_SERVICE_URL', env('OBSERVING_SKY_MICROSERVICE_BASE', 'http://sky:8010')),
        'translate_path' => env('TRANSLATION_SERVICE_TRANSLATE_PATH', '/translate'),
        'diagnostics_path' => env('TRANSLATION_SERVICE_DIAGNOSTICS_PATH', '/diagnostics'),
        'timeout_seconds' => (int) env('TRANSLATION_TIMEOUT_SECONDS', 12),
        'connect_timeout_seconds' => (int) env('TRANSLATION_CONNECT_TIMEOUT_SECONDS', 3),
        'retries' => (int) env('TRANSLATION_RETRIES', 2),
        'retry_sleep_ms' => (int) env('TRANSLATION_RETRY_SLEEP_MS', 250),
        'internal_token' => env('TRANSLATION_INTERNAL_TOKEN', env('INTERNAL_TOKEN', '')),
    ],

    'nasa' => [
        'key' => env('NASA_API_KEY', env('NASA_APOD_API_KEY', '')),
        'apod_api_key' => env('NASA_APOD_API_KEY', env('NASA_API_KEY', '')),
    ],

    'giphy' => [
        'api_key' => env('GIPHY_API_KEY', ''),
        'base_url' => env('GIPHY_BASE_URL', 'https://api.giphy.com/v1'),
        'timeout_seconds' => (int) env('GIPHY_TIMEOUT_SECONDS', 4),
        'connect_timeout_seconds' => (int) env('GIPHY_CONNECT_TIMEOUT_SECONDS', 2),
        'cache_ttl_seconds' => (int) env('GIPHY_CACHE_TTL_SECONDS', 600),
        'global_hourly_limit' => (int) env('GIPHY_GLOBAL_HOURLY_LIMIT', 80),
        'allowed_media_hosts' => array_values(array_filter(array_map(
            static fn (string $host): string => strtolower(trim($host)),
            explode(',', (string) env('GIPHY_ALLOWED_MEDIA_HOSTS', 'media.giphy.com,i.giphy.com,media0.giphy.com,media1.giphy.com,media2.giphy.com,media3.giphy.com,media4.giphy.com'))
        ))),
    ],

    'turnstile' => [
        'enabled' => env('TURNSTILE_ENABLED', true),
        'secret_key' => env('TURNSTILE_SECRET_KEY'),
        'verify_url' => 'https://challenges.cloudflare.com/turnstile/v0/siteverify',
    ],

];
