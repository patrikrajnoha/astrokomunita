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
];

