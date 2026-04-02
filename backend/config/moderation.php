<?php

return [
    'enabled' => env('MODERATION_ENABLED', true),

    // Extra hardening for non-post image uploads (avatar/cover, observations, poll options).
    'enforce_upload_image_scan' => (bool) env('MODERATION_ENFORCE_UPLOAD_IMAGE_SCAN', env('APP_ENV', 'production') !== 'testing'),

    'base_url' => rtrim((string) env('MODERATION_BASE_URL', 'http://127.0.0.1:8090'), '/'),

    'internal_token' => (string) env('MODERATION_INTERNAL_TOKEN', ''),

    'timeout_seconds' => (float) env('MODERATION_TIMEOUT_SECONDS', 8),

    'connect_timeout_seconds' => (float) env('MODERATION_CONNECT_TIMEOUT_SECONDS', 2),

    'retries' => (int) env('MODERATION_RETRIES', 2),

    'retry_sleep_ms' => (int) env('MODERATION_RETRY_SLEEP_MS', 250),

    'fallback_policy' => env('MODERATION_FALLBACK_POLICY', 'pending_blur_retry'),

    // Must match moderation microservice MAX_IMAGE_BYTES to avoid 413 loops.
    'image_max_bytes' => (int) env('MODERATION_IMAGE_MAX_BYTES', 20 * 1024 * 1024),
    // JPEG resize settings for payload_too_large fallback.
    'image_resize_max_width' => (int) env('MODERATION_IMAGE_RESIZE_MAX_WIDTH', 1600),
    'image_resize_jpeg_quality' => (int) env('MODERATION_IMAGE_RESIZE_JPEG_QUALITY', 78),
    // Safety cap for GD raster processing fallback (avoid OOM on giant images).
    'image_resize_gd_max_pixels' => (int) env('MODERATION_IMAGE_RESIZE_GD_MAX_PIXELS', 16000000),

    'thresholds' => [
        'text_flag_threshold' => (float) env('MODERATION_TEXT_FLAG_THRESHOLD', 0.70),
        'text_block_threshold' => (float) env('MODERATION_TEXT_BLOCK_THRESHOLD', 0.90),
        'image_flag_threshold' => (float) env('MODERATION_IMAGE_FLAG_THRESHOLD', 0.60),
        'image_block_threshold' => (float) env('MODERATION_IMAGE_BLOCK_THRESHOLD', 0.85),
    ],
];
