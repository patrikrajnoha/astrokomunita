<?php

return [
    'sentry' => [
        'enabled' => (bool) env('SENTRY_ENABLED', false),
        'dsn' => (string) env('SENTRY_DSN', ''),
        'environment' => (string) env('SENTRY_ENV', env('APP_ENV', 'production')),
        'release' => (string) env('SENTRY_RELEASE', ''),
        'timeout_seconds' => (float) env('SENTRY_TIMEOUT_SECONDS', 2.5),
    ],
];
