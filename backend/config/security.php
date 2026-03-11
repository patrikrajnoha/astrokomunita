<?php

return [
    'headers' => [
        'enabled' => filter_var(env('SECURITY_HEADERS_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
        'x_frame_options' => env('SECURITY_HEADER_X_FRAME_OPTIONS', 'SAMEORIGIN'),
        'x_content_type_options' => env('SECURITY_HEADER_X_CONTENT_TYPE_OPTIONS', 'nosniff'),
        'referrer_policy' => env('SECURITY_HEADER_REFERRER_POLICY', 'strict-origin-when-cross-origin'),
        'permissions_policy' => env('SECURITY_HEADER_PERMISSIONS_POLICY', 'camera=(), geolocation=(), microphone=()'),
        'x_permitted_cross_domain_policies' => env('SECURITY_HEADER_X_PERMITTED_CROSS_DOMAIN_POLICIES', 'none'),
        'hsts_max_age' => (int) env('SECURITY_HEADER_HSTS_MAX_AGE', 31536000),
        'hsts_include_subdomains' => filter_var(env('SECURITY_HEADER_HSTS_INCLUDE_SUBDOMAINS', true), FILTER_VALIDATE_BOOLEAN),
        'hsts_preload' => filter_var(env('SECURITY_HEADER_HSTS_PRELOAD', false), FILTER_VALIDATE_BOOLEAN),
    ],
    'health' => [
        'expose_diagnostics' => filter_var(env('HEALTH_EXPOSE_DIAGNOSTICS', false), FILTER_VALIDATE_BOOLEAN),
    ],
];
