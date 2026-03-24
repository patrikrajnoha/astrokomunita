<?php

$ollamaBaseUrl = env('OLLAMA_BASE_URL', env('AI_OLLAMA_BASE_URL', 'http://127.0.0.1:11434'));
$ollamaModel = env('OLLAMA_MODEL', env('AI_OLLAMA_MODEL_NAME', 'mistral'));
$ollamaTimeoutSeconds = (int) env('OLLAMA_TIMEOUT', env('AI_OLLAMA_TIMEOUT_SECONDS', 60));
$ollamaRetryBackoffBaseMs = (int) env(
    'OLLAMA_RETRY_BACKOFF_BASE_MS',
    env('AI_OLLAMA_RETRY_BACKOFF_BASE_MS', env('OLLAMA_RETRY_SLEEP_MS', 250))
);

return [
    'ollama_base_url' => $ollamaBaseUrl,
    'ollama_refinement_enabled' => filter_var(env('AI_OLLAMA_REFINEMENT_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
    'ollama_timeout_seconds' => $ollamaTimeoutSeconds,
    'ollama_model_name' => $ollamaModel,
    'ollama_retry_attempts' => (int) env('AI_OLLAMA_RETRY_ATTEMPTS', 3),
    'ollama_retry_backoff_base_ms' => $ollamaRetryBackoffBaseMs,
    'ollama_max_tokens_description' => (int) env('AI_OLLAMA_MAX_TOKENS_DESCRIPTION', 420),
    'ollama_jitter_ms' => array_values(array_filter(array_map(
        static fn (string $value): int => (int) trim($value),
        explode(',', (string) env('AI_OLLAMA_JITTER_MS', '200,500'))
    ), static fn (int $value): bool => $value >= 0)),
    'ollama_safe_concurrency_default' => (int) env('AI_OLLAMA_SAFE_CONCURRENCY_DEFAULT', 2),
    'ollama_refinement_temperature' => (float) env('AI_OLLAMA_REFINEMENT_TEMPERATURE', 0.3),
    'ollama_refinement_max_tokens' => (int) env('AI_OLLAMA_REFINEMENT_MAX_TOKENS', 700),
    'ollama_refinement_min_description_length' => (int) env('AI_OLLAMA_REFINEMENT_MIN_DESCRIPTION_LENGTH', 0),

    'blog_tag_suggestion' => [
        'model' => env('AI_BLOG_TAG_SUGGESTION_MODEL', env('EVENTS_AI_MODEL', $ollamaModel)),
        'temperature' => (float) env('AI_BLOG_TAG_SUGGESTION_TEMPERATURE', env('EVENTS_AI_HUMANIZED_TEMPERATURE', 0.25)),
        'num_predict' => (int) env('AI_BLOG_TAG_SUGGESTION_NUM_PREDICT', env('EVENTS_AI_HUMANIZED_NUM_PREDICT', 320)),
        'timeout_seconds' => (int) env('AI_BLOG_TAG_SUGGESTION_TIMEOUT_SECONDS', env('EVENTS_AI_TIMEOUT', 40)),
        'retry_backoff_base_ms' => (int) env(
            'AI_BLOG_TAG_SUGGESTION_RETRY_BACKOFF_BASE_MS',
            env('EVENTS_AI_RETRY_BACKOFF_BASE_MS', $ollamaRetryBackoffBaseMs)
        ),
    ],

    'ollama' => [
        'base_url' => $ollamaBaseUrl,
        'generate_path' => env('OLLAMA_GENERATE_PATH', '/api/generate'),
        'model' => $ollamaModel,
        'timeout' => $ollamaTimeoutSeconds,
        'connect_timeout' => (int) env('OLLAMA_CONNECT_TIMEOUT', 3),
        'retry' => (int) env('OLLAMA_RETRY', 1),
        'retry_sleep_ms' => (int) env('OLLAMA_RETRY_SLEEP_MS', 250),
        'max_retries' => (int) env('OLLAMA_MAX_RETRIES', 2),
        'retry_backoff_base_ms' => $ollamaRetryBackoffBaseMs,
        'verify_ssl' => filter_var(env('OLLAMA_VERIFY_SSL', true), FILTER_VALIDATE_BOOLEAN),
        'internal_token' => env('OLLAMA_INTERNAL_TOKEN', env('INTERNAL_TOKEN', '')),
        'temperature' => (float) env('OLLAMA_TEMPERATURE', 0.1),
        'num_predict' => (int) env('OLLAMA_NUM_PREDICT', 256),
    ],
];
