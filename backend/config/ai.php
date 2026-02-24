<?php

return [
    'ollama_base_url' => env('AI_OLLAMA_BASE_URL', env('OLLAMA_BASE_URL', 'http://127.0.0.1:11434')),
    'ollama_refinement_enabled' => filter_var(env('AI_OLLAMA_REFINEMENT_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
    'ollama_timeout_seconds' => (int) env('AI_OLLAMA_TIMEOUT_SECONDS', env('OLLAMA_TIMEOUT', 60)),
    'ollama_model_name' => env('AI_OLLAMA_MODEL_NAME', env('OLLAMA_MODEL', 'mistral')),
    'ollama_retry_attempts' => (int) env('AI_OLLAMA_RETRY_ATTEMPTS', 3),
    'ollama_retry_backoff_seconds' => array_values(array_filter(array_map(
        static fn (string $value): int => (int) trim($value),
        explode(',', (string) env('AI_OLLAMA_RETRY_BACKOFF_SECONDS', '1,3,7'))
    ), static fn (int $value): bool => $value >= 0)),
    'ollama_max_tokens_description' => (int) env('AI_OLLAMA_MAX_TOKENS_DESCRIPTION', 420),
    'ollama_jitter_ms' => array_values(array_filter(array_map(
        static fn (string $value): int => (int) trim($value),
        explode(',', (string) env('AI_OLLAMA_JITTER_MS', '200,500'))
    ), static fn (int $value): bool => $value >= 0)),
    'ollama_safe_concurrency_default' => (int) env('AI_OLLAMA_SAFE_CONCURRENCY_DEFAULT', 2),
    'ollama_refinement_temperature' => (float) env('AI_OLLAMA_REFINEMENT_TEMPERATURE', 0.3),
    'ollama_refinement_max_tokens' => (int) env('AI_OLLAMA_REFINEMENT_MAX_TOKENS', 700),

    'ollama' => [
        'base_url' => env('OLLAMA_BASE_URL', env('AI_OLLAMA_BASE_URL', 'http://127.0.0.1:11434')),
        'generate_path' => env('OLLAMA_GENERATE_PATH', '/api/generate'),
        'model' => env('OLLAMA_MODEL', 'mistral'),
        'timeout' => (int) env('OLLAMA_TIMEOUT', 60),
        'connect_timeout' => (int) env('OLLAMA_CONNECT_TIMEOUT', 3),
        'retry' => (int) env('OLLAMA_RETRY', 1),
        'retry_sleep_ms' => (int) env('OLLAMA_RETRY_SLEEP_MS', 250),
        'verify_ssl' => filter_var(env('OLLAMA_VERIFY_SSL', true), FILTER_VALIDATE_BOOLEAN),
        'internal_token' => env('OLLAMA_INTERNAL_TOKEN', env('INTERNAL_TOKEN', '')),
        'temperature' => (float) env('OLLAMA_TEMPERATURE', 0.1),
        'num_predict' => (int) env('OLLAMA_NUM_PREDICT', 256),
    ],
];
