<?php

return [
    'default_timezone' => env('OBSERVING_DEFAULT_TZ', 'Europe/Bratislava'),

    'sky_context' => [
        'fallback_lat' => env('SKY_FALLBACK_LAT', 48.1486),
        'fallback_lon' => env('SKY_FALLBACK_LON', 17.1077),
        'fallback_tz' => env('SKY_FALLBACK_TZ', 'Europe/Bratislava'),
    ],

    'sky' => [
        'weather_cache_ttl_minutes' => env('SKY_WEATHER_CACHE_TTL_MINUTES', 10),
        'viewing_forecast_cache_ttl_minutes' => env('SKY_VIEWING_FORECAST_CACHE_TTL_MINUTES', 30),
        'astronomy_cache_ttl_minutes' => env('SKY_ASTRONOMY_CACHE_TTL_MINUTES', 5),
        'astronomy_cache_ttl_hours' => env('SKY_ASTRONOMY_CACHE_TTL_HOURS', 6),
        'astronomy_precision_bucket_minutes' => env('SKY_ASTRONOMY_PRECISION_BUCKET_MINUTES', 1),
        'moon_phases_cache_ttl_minutes' => env('SKY_MOON_PHASES_CACHE_TTL_MINUTES', 5),
        'moon_phases_cache_ttl_hours' => env('SKY_MOON_PHASES_CACHE_TTL_HOURS', 12),
        'moon_phases_precision_bucket_minutes' => env('SKY_MOON_PHASES_PRECISION_BUCKET_MINUTES', 1),
        'visible_planets_cache_ttl_minutes' => env('SKY_VISIBLE_PLANETS_CACHE_TTL_MINUTES', 10),
        'iss_preview_cache_ttl_minutes' => env('SKY_ISS_PREVIEW_CACHE_TTL_MINUTES', 15),
        'light_pollution_cache_ttl_hours' => env('SKY_LIGHT_POLLUTION_CACHE_TTL_HOURS', 24),
        'internal_token' => env('SKY_INTERNAL_TOKEN', ''),
    ],

    'cache' => [
        'ttl_minutes' => env('OBSERVING_CACHE_TTL_MINUTES', 15),
        'partial_ttl_minutes' => env('OBSERVING_CACHE_PARTIAL_TTL_MINUTES', 5),
        'all_unavailable_ttl_seconds' => env('OBSERVING_CACHE_ALL_UNAVAILABLE_TTL_SECONDS', 90),
    ],

    'concurrency_driver' => env('OBSERVING_CONCURRENCY_DRIVER', 'process'),

    'circuit_breaker' => [
        'failure_threshold' => env('OBSERVING_CB_FAILURE_THRESHOLD', 3),
        'failure_ttl_seconds' => env('OBSERVING_CB_FAILURE_TTL_SECONDS', 300),
        'cooldown_seconds' => env('OBSERVING_CB_COOLDOWN_SECONDS', 120),
    ],

    'http' => [
        'timeout_seconds' => env('OBSERVING_HTTP_TIMEOUT_SECONDS', 8),
        'retry_times' => env('OBSERVING_HTTP_RETRY_TIMES', 2),
        'retry_sleep_ms' => env('OBSERVING_HTTP_RETRY_SLEEP_MS', 200),
        'local_ca_bundle_path' => env('OBSERVING_CA_BUNDLE_PATH', storage_path('certs/cacert.pem')),
    ],

    'providers' => [
        'usno_url' => env('USNO_ONEDAY_URL', 'https://aa.usno.navy.mil/api/rstt/oneday'),
        'open_meteo_url' => env('OPEN_METEO_FORECAST_URL', 'https://api.open-meteo.com/v1/forecast'),
        'iss_pass_url' => env('ISS_PASS_PROVIDER_URL', 'http://api.open-notify.org/iss-pass.json'),
        'light_pollution_url' => env('LIGHT_POLLUTION_PROVIDER_URL', ''),
        'openaq' => [
            'key' => env('OPENAQ_API_KEY'),
            'base_url' => env('OPENAQ_BASE_URL', 'https://api.openaq.org/v3'),
        ],
        'openaq_radius_meters' => env('OPENAQ_RADIUS_METERS', 25000),
    ],

    'thresholds' => [
        'moon' => [
            'warning_min_pct' => env('OBSERVING_MOON_WARNING_MIN_PCT', 90),
        ],
        'humidity' => [
            'ok_max' => env('OBSERVING_HUMIDITY_OK_MAX', 60),
            'warn_max' => env('OBSERVING_HUMIDITY_WARN_MAX', 80),
        ],
        'pm25' => [
            'ok_max' => env('OBSERVING_PM25_OK_MAX', 15),
            'warn_max' => env('OBSERVING_PM25_WARN_MAX', 35),
        ],
        'pm10' => [
            'ok_max' => env('OBSERVING_PM10_OK_MAX', 30),
            'warn_max' => env('OBSERVING_PM10_WARN_MAX', 60),
        ],
    ],

    'defaults' => [
        'evening_target_time' => env('OBSERVING_EVENING_TARGET_TIME', '21:00'),
    ],

    'sky_summary' => [
        'cache_ttl_minutes' => env('OBSERVING_SKY_CACHE_TTL_MINUTES', 60),
        'microservice_base' => env('OBSERVING_SKY_MICROSERVICE_BASE', env('OBSERVING_SKY_MICROSERVICE_URL', 'http://sky:8010')),
        'endpoint_path' => env('OBSERVING_SKY_ENDPOINT_PATH', '/sky-summary'),
        'iss_preview_endpoint_path' => env('OBSERVING_SKY_ISS_PREVIEW_ENDPOINT_PATH', '/iss-preview'),
        'health_path' => env('OBSERVING_SKY_HEALTH_PATH', '/health'),
        'timeout_seconds' => env('OBSERVING_SKY_TIMEOUT_SECONDS', 12),
        'retry_times' => env('OBSERVING_SKY_RETRY_TIMES', 1),
        'retry_sleep_ms' => env('OBSERVING_SKY_RETRY_SLEEP_MS', 200),
    ],
];
