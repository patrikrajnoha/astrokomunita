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
    'next_meteor_shower' => [
        'cache_ttl_seconds' => (int) env('NEXT_METEOR_SHOWER_WIDGET_CACHE_TTL_SECONDS', 300),
    ],
    'neo_watchlist' => [
        'cache_ttl_minutes' => (int) env('NEO_WATCHLIST_WIDGET_CACHE_TTL_MINUTES', 30),
        'last_known_ttl_minutes' => (int) env('NEO_WATCHLIST_LAST_KNOWN_TTL_MINUTES', 720),
    ],
    'upcoming_launches' => [
        'cache_ttl_minutes' => (int) env('UPCOMING_LAUNCHES_WIDGET_CACHE_TTL_MINUTES', 15),
        'last_known_ttl_minutes' => (int) env('UPCOMING_LAUNCHES_LAST_KNOWN_TTL_MINUTES', 360),
    ],
];
