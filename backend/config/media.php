<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Media Storage Disk
    |--------------------------------------------------------------------------
    |
    | Public user uploads should live on a dedicated disk that can be switched
    | per environment. Local development uses "public" by default, production
    | can switch this to "cloud".
    |
    */
    'disk' => env('FILES_DISK', 'public'),
    'private_disk' => env('FILES_PRIVATE_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Post Attachment Validation
    |--------------------------------------------------------------------------
    */
    'post_attachment_max_kb' => 10240,
    'post_attachment_mimes' => [
        'jpg',
        'jpeg',
        'png',
        'webp',
        'gif',
        'mp4',
        'webm',
        'mov',
        'pdf',
        'txt',
        'doc',
        'docx',
    ],
    'post_image_allowed_mimes' => [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/gif',
    ],
    'post_image_max_pixels' => 10000,
    'post_image_web_max_width' => 1600,
    'post_image_webp_quality' => 80,
    'post_image_jpeg_quality' => 82,

    /*
    |--------------------------------------------------------------------------
    | Poll Option Image Validation
    |--------------------------------------------------------------------------
    */
    'poll_option_image_max_kb' => 5120,
];
