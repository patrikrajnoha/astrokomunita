<?php

return [
    'ollama' => [
        'base_url' => env('OLLAMA_BASE_URL', 'http://127.0.0.1:11434'),
        'generate_path' => env('OLLAMA_GENERATE_PATH', '/api/generate'),
        'model' => env('OLLAMA_MODEL', 'mistral'),
        'timeout' => (int) env('OLLAMA_TIMEOUT', 60),
        'connect_timeout' => (int) env('OLLAMA_CONNECT_TIMEOUT', 5),
        'retry' => (int) env('OLLAMA_RETRY', 1),
        'retry_sleep_ms' => (int) env('OLLAMA_RETRY_SLEEP_MS', 250),
        'verify_ssl' => filter_var(env('OLLAMA_VERIFY_SSL', true), FILTER_VALIDATE_BOOLEAN),
        'internal_token' => env('OLLAMA_INTERNAL_TOKEN', env('INTERNAL_TOKEN', '')),
        'temperature' => (float) env('OLLAMA_TEMPERATURE', 0.1),
        'num_predict' => (int) env('OLLAMA_NUM_PREDICT', 256),
    ],
];

