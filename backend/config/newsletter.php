<?php

return [
    'queue' => env('NEWSLETTER_QUEUE', 'default'),
    'chunk_size' => (int) env('NEWSLETTER_CHUNK_SIZE', 100),
    'top_articles_limit' => (int) env('NEWSLETTER_TOP_ARTICLES_LIMIT', 4),
    'frontend_base_url' => env('NEWSLETTER_FRONTEND_BASE_URL', env('APP_URL', 'http://localhost')),
    'fallback_tips' => [
        'Astronomicky tip tyzdna: Pozorujte z miesta s co najmensim svetelnym smogom a doprajte ociam aspon 20 minut adaptacie na tmu.',
        'Astronomicky tip tyzdna: Skontrolujte priehladnost oblohy, vezmite teple oblecenie a pripravte si jednoduchy plan pozorovania pred odchodom.',
        'Astronomicky tip tyzdna: Pri slabych objektoch pouzite periferny pohlad a vyhybajte sa jasnym displejom, aby ste nestratili nocne videnie.',
    ],
];
