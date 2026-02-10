<?php

return [
    'default_timezone' => env('OBSERVING_DEFAULT_TZ', 'Europe/Bratislava'),

    'cache' => [
        'ttl_minutes' => env('OBSERVING_CACHE_TTL_MINUTES', 15),
        'partial_ttl_minutes' => env('OBSERVING_CACHE_PARTIAL_TTL_MINUTES', 5),
        'all_unavailable_ttl_seconds' => env('OBSERVING_CACHE_ALL_UNAVAILABLE_TTL_SECONDS', 90),
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
];
