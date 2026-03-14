<?php

return [
    'articles_widget' => [
        'cache_ttl_seconds' => (int) env('ARTICLES_WIDGET_CACHE_TTL_SECONDS', 60),
    ],
    'upcoming_events' => [
        'cache_ttl_seconds' => (int) env('UPCOMING_EVENTS_WIDGET_CACHE_TTL_SECONDS', 120),
    ],
    'next_eclipse' => [
        'cache_ttl_seconds' => (int) env('NEXT_ECLIPSE_WIDGET_CACHE_TTL_SECONDS', 300),
    ],
];
