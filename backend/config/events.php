<?php

return [
    'default_visibility' => 1,
    'source_timezone' => 'Europe/Bratislava',
    'crawler_ssl_verify' => filter_var(env('EVENTS_CRAWLER_SSL_VERIFY', true), FILTER_VALIDATE_BOOLEAN),
    'crawler_ssl_ca_bundle' => env('EVENTS_CRAWLER_CA_BUNDLE'),
    'timezone' => env('EVENTS_DISPLAY_TIMEZONE', 'Europe/Bratislava'),
    'description_template_min_length' => (int) env('EVENTS_DESCRIPTION_TEMPLATE_MIN_LENGTH', 40),
    'refine_descriptions_with_ollama' => filter_var(env('EVENTS_REFINE_DESCRIPTIONS_WITH_OLLAMA', false), FILTER_VALIDATE_BOOLEAN),
    'ai' => [
        'description_mode' => env('EVENTS_AI_DESCRIPTION_MODE', 'template'),
        'model' => env('EVENTS_AI_MODEL', config('ai.ollama.model', 'mistral')),
        'temperature' => (float) env('EVENTS_AI_TEMPERATURE', 0.2),
        'num_predict' => (int) env('EVENTS_AI_NUM_PREDICT', 420),
        'timeout' => (int) env('EVENTS_AI_TIMEOUT', 45),
    ],
    'astropixels' => [
        'min_year' => 2021,
        'max_year' => 2030,
        'base_url_pattern' => 'https://astropixels.com/almanac/almanac21/almanac%dcet.html',
    ],
    'imo' => [
        'url' => env('EVENTS_IMO_URL', 'https://www.imo.net/resources/calendar/'),
    ],

    'types' => [
        'meteors',
        'meteor_shower',
        'eclipse',
        'eclipse_lunar',
        'eclipse_solar',
        'conjunction',
        'planetary_event',
        'comet',
        'asteroid',
        'space_event',
        'observation_window',
        'planet',
        'mission',
        'other',
    ],

    'regions' => [
        'sk',
        'eu',
        'global',
    ],

    'defaults' => [
        'region' => 'global',
        'event_types' => [],
    ],
];
