<?php

return [
    'default_visibility' => 1,
    'source_timezone' => 'Europe/Bratislava',
    'astropixels' => [
        'min_year' => 2021,
        'max_year' => 2030,
        'base_url_pattern' => 'https://astropixels.com/almanac/almanac21/almanac%dcet.html',
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
