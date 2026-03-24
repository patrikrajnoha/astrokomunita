<?php

namespace App\Services\AI;

use App\Support\Http\SslVerificationPolicy;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Throwable;

class OllamaClient
{
    /**
     * @param array<string,mixed> $options
     * @return array{text:string,model:string,duration_ms:int,retry_count:int,raw:array<string,mixed>}
     *
     * @throws OllamaClientException
     */
    public function generate(string $prompt, ?string $system = null, array $options = []): array
    {
        $startedAt = microtime(true);
        $config = (array) config('ai.ollama', []);
        $model = trim((string) ($options['model'] ?? ($config['model'] ?? 'mistral')));

        if ($model === '') {
            throw new OllamaClientException('Ollama model is not configured.', 'ollama_model_missing');
        }

        $payload = [
            'model' => $model,
            'prompt' => $prompt,
            'stream' => false,
            'options' => [
                'temperature' => (float) ($options['temperature'] ?? ($config['temperature'] ?? 0.1)),
                'top_p' => (float) ($options['top_p'] ?? ($config['top_p'] ?? 0.9)),
                'num_predict' => (int) ($options['num_predict'] ?? ($config['num_predict'] ?? 256)),
            ],
        ];
        $stop = $options['stop'] ?? null;
        if (is_array($stop) && $stop !== []) {
            $payload['options']['stop'] = array_values(array_filter(array_map(
                static fn (mixed $item): string => trim((string) $item),
                $stop
            ), static fn (string $item): bool => $item !== ''));
        }

        if ($system !== null && trim($system) !== '') {
            $payload['system'] = $system;
        }

        $format = $options['format'] ?? null;
        if (is_string($format) && $format !== '') {
            $payload['format'] = $format;
        }

        $verifyOption = app(SslVerificationPolicy::class)->resolveVerifyOption(
            allowInsecure: ! (bool) ($config['verify_ssl'] ?? true)
        );

        $maxRetries = $this->resolveMaxRetries($options, $config);
        $backoffBaseMs = $this->resolveRetryBackoffBaseMs($options, $config);
        $retryCount = 0;

        while (true) {
            try {
                $response = Http::baseUrl((string) ($config['base_url'] ?? 'http://127.0.0.1:11434'))
                    ->timeout((int) ($options['timeout'] ?? ($config['timeout'] ?? 60)))
                    ->connectTimeout((int) ($options['connect_timeout'] ?? ($config['connect_timeout'] ?? 5)))
                    ->withOptions([
                        'verify' => $verifyOption,
                    ])
                    ->withAttributes(['ssl_verify' => $verifyOption])
                    ->acceptJson()
                    ->withHeaders($this->resolveHeaders($config))
                    ->post((string) ($config['generate_path'] ?? '/api/generate'), $payload);
            } catch (ConnectionException $exception) {
                if ($retryCount < $maxRetries) {
                    $this->sleepForRetry($retryCount, $backoffBaseMs);
                    $retryCount++;
                    continue;
                }

                if ($this->isTimeoutException($exception)) {
                    throw new OllamaClientException('Ollama request timed out.', 'ollama_timeout_error');
                }

                throw new OllamaClientException('Ollama connection failed.', 'ollama_connection_error');
            } catch (Throwable $exception) {
                if ($this->isRetryableException($exception) && $retryCount < $maxRetries) {
                    $this->sleepForRetry($retryCount, $backoffBaseMs);
                    $retryCount++;
                    continue;
                }

                if ($this->isTimeoutException($exception)) {
                    throw new OllamaClientException('Ollama request timed out.', 'ollama_timeout_error');
                }

                throw new OllamaClientException('Ollama request failed.', 'ollama_service_error');
            }

            if (! $response->successful()) {
                $status = $response->status();
                if ($this->isRetryableStatus($status) && $retryCount < $maxRetries) {
                    $this->sleepForRetry($retryCount, $backoffBaseMs);
                    $retryCount++;
                    continue;
                }

                throw new OllamaClientException(
                    'Ollama failed with HTTP ' . $status . '.',
                    'ollama_http_' . $status,
                    $status
                );
            }

            $data = $response->json();
            if (! is_array($data)) {
                throw new OllamaClientException('Ollama response is invalid.', 'ollama_invalid_response');
            }

            $text = $data['response'] ?? null;
            if (! is_string($text)) {
                throw new OllamaClientException('Ollama response text is missing.', 'ollama_missing_text');
            }

            return [
                'text' => trim($text),
                'model' => (string) ($data['model'] ?? $model),
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'retry_count' => $retryCount,
                'raw' => $data,
            ];
        }
    }

    /**
     * @param array<string,mixed> $config
     * @return array<string,string>
     */
    private function resolveHeaders(array $config): array
    {
        $headers = [];
        $internalToken = trim((string) ($config['internal_token'] ?? ''));

        if ($internalToken !== '') {
            $headers['X-Internal-Token'] = $internalToken;
        }

        return $headers;
    }

    private function isTimeoutException(Throwable $exception): bool
    {
        $message = strtolower($exception->getMessage());

        return str_contains($message, 'timed out')
            || str_contains($message, 'timeout')
            || str_contains($message, 'curl error 28');
    }

    private function isRetryableException(Throwable $exception): bool
    {
        if ($exception instanceof ConnectionException) {
            return true;
        }

        $message = strtolower($exception->getMessage());
        return str_contains($message, 'connection')
            || str_contains($message, 'timed out')
            || str_contains($message, 'timeout')
            || str_contains($message, 'overload');
    }

    private function isRetryableStatus(int $status): bool
    {
        return $status === 429 || $status >= 500;
    }

    /**
     * The policy for event generation uses up to 2 retries with exponential backoff.
     *
     * @param array<string,mixed> $options
     * @param array<string,mixed> $config
     */
    private function resolveMaxRetries(array $options, array $config): int
    {
        $configured = $options['max_retries'] ?? $config['max_retries'] ?? null;
        if ($configured === null) {
            // Legacy alias kept for backward compatibility.
            $configured = $options['retry'] ?? $config['retry'] ?? 2;
        }

        $configured = (int) $configured;
        return max(0, min(2, $configured));
    }

    /**
     * @param array<string,mixed> $options
     * @param array<string,mixed> $config
     */
    private function resolveRetryBackoffBaseMs(array $options, array $config): int
    {
        $configured = $options['retry_backoff_base_ms'] ?? $config['retry_backoff_base_ms'] ?? null;
        if ($configured === null) {
            // Legacy alias kept for backward compatibility.
            $configured = $options['retry_sleep_ms'] ?? $config['retry_sleep_ms'] ?? 250;
        }

        $configured = (int) $configured;
        return max(50, $configured);
    }

    private function sleepForRetry(int $retryIndex, int $baseMs): void
    {
        $delayMs = $baseMs * (2 ** max(0, $retryIndex));
        $delayMs = min(5_000, $delayMs);

        usleep($delayMs * 1_000);
    }
}
