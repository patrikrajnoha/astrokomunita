<?php

return [
    'enabled' => env('MODERATION_ENABLED', true),

    'base_url' => rtrim((string) env('MODERATION_BASE_URL', 'http://127.0.0.1:8090'), '/'),

    'internal_token' => (string) env('MODERATION_INTERNAL_TOKEN', ''),

    'timeout_seconds' => (float) env('MODERATION_TIMEOUT_SECONDS', 8),

    'connect_timeout_seconds' => (float) env('MODERATION_CONNECT_TIMEOUT_SECONDS', 2),

    'retries' => (int) env('MODERATION_RETRIES', 2),

    'retry_sleep_ms' => (int) env('MODERATION_RETRY_SLEEP_MS', 250),

    'fallback_policy' => env('MODERATION_FALLBACK_POLICY', 'pending_blur_retry'),

    'thresholds' => [
        'text_flag_threshold' => (float) env('MODERATION_TEXT_FLAG_THRESHOLD', 0.70),
        'text_block_threshold' => (float) env('MODERATION_TEXT_BLOCK_THRESHOLD', 0.90),
        'image_flag_threshold' => (float) env('MODERATION_IMAGE_FLAG_THRESHOLD', 0.60),
        'image_block_threshold' => (float) env('MODERATION_IMAGE_BLOCK_THRESHOLD', 0.85),
    ],
];
