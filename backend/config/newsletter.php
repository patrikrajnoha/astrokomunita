<?php

return [
    'queue' => env('NEWSLETTER_QUEUE', 'default'),
    'chunk_size' => (int) env('NEWSLETTER_CHUNK_SIZE', 100),
    'batch_size' => (int) env('NEWSLETTER_BATCH_SIZE', 200),
    'rate_limit_per_minute' => (int) env('NEWSLETTER_RATE_LIMIT_PER_MINUTE', 600),
    'sleep_ms_between_batches' => (int) env('NEWSLETTER_SLEEP_MS_BETWEEN_BATCHES', 0),
    'max_recipients_per_run' => (int) env('NEWSLETTER_MAX_RECIPIENTS_PER_RUN', 0),
    'preview_rate_limit_per_minute' => (int) env('NEWSLETTER_PREVIEW_RATE_LIMIT_PER_MINUTE', 20),
    'disable_throttling_in_tests' => filter_var(env('NEWSLETTER_DISABLE_THROTTLING_IN_TESTS', true), FILTER_VALIDATE_BOOL),
    'top_articles_limit' => (int) env('NEWSLETTER_TOP_ARTICLES_LIMIT', 4),
    'frontend_base_url' => env('NEWSLETTER_FRONTEND_BASE_URL', env('APP_URL', 'http://localhost')),
    'unsubscribe_url_ttl_days' => (int) env('NEWSLETTER_UNSUBSCRIBE_URL_TTL_DAYS', 30),
    'fallback_tips' => [
        'Astronomicky tip tyzdna: Pozorujte z miesta s co najmensim svetelnym smogom a doprajte ociam aspon 20 minut adaptacie na tmu.',
        'Astronomicky tip tyzdna: Skontrolujte priehladnost oblohy, vezmite teple oblecenie a pripravte si jednoduchy plan pozorovania pred odchodom.',
        'Astronomicky tip tyzdna: Pri slabych objektoch pouzite periferny pohlad a vyhybajte sa jasnym displejom, aby ste nestratili nocne videnie.',
    ],
];
