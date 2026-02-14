<?php

return [
    'default_visibility' => 1,

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
