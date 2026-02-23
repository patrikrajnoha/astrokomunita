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
     * @return array{text:string,model:string,duration_ms:int,raw:array<string,mixed>}
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

        if ($system !== null && trim($system) !== '') {
            $payload['system'] = $system;
        }

        $verifyOption = app(SslVerificationPolicy::class)->resolveVerifyOption(
            allowInsecure: ! (bool) ($config['verify_ssl'] ?? true)
        );

        try {
            $response = Http::baseUrl((string) ($config['base_url'] ?? 'http://127.0.0.1:11434'))
                ->timeout((int) ($options['timeout'] ?? ($config['timeout'] ?? 60)))
                ->connectTimeout((int) ($config['connect_timeout'] ?? 5))
                ->retry(
                    (int) ($config['retry'] ?? 1),
                    (int) ($config['retry_sleep_ms'] ?? 250),
                    null,
                    false
                )
                ->withOptions([
                    'verify' => $verifyOption,
                ])
                ->withAttributes(['ssl_verify' => $verifyOption])
                ->acceptJson()
                ->withHeaders($this->resolveHeaders($config))
                ->post((string) ($config['generate_path'] ?? '/api/generate'), $payload);
        } catch (ConnectionException) {
            throw new OllamaClientException('Ollama connection failed.', 'ollama_connection_error');
        } catch (Throwable) {
            throw new OllamaClientException('Ollama request failed.', 'ollama_service_error');
        }

        if (! $response->successful()) {
            throw new OllamaClientException(
                'Ollama failed with HTTP ' . $response->status() . '.',
                'ollama_http_' . $response->status(),
                $response->status()
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
            'raw' => $data,
        ];
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
}
