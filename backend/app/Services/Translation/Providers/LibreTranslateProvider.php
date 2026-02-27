<?php

namespace App\Services\Translation\Providers;

use App\Services\Translation\Contracts\TranslationProviderInterface;
use App\Services\Translation\TranslationResult;
use App\Services\Translation\TranslationServiceException;
use App\Support\Http\SslVerificationPolicy;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Throwable;

class LibreTranslateProvider implements TranslationProviderInterface
{
    public function translate(string $text, string $from, string $to): TranslationResult
    {
        $startedAt = microtime(true);
        $config = (array) config('translation.libretranslate', []);
        $verifyOption = app(SslVerificationPolicy::class)->resolveVerifyOption(
            allowInsecure: ! (bool) ($config['verify_ssl'] ?? true)
        );

        try {
            $response = Http::baseUrl((string) ($config['base_url'] ?? 'http://127.0.0.1:5000'))
                ->timeout((int) ($config['timeout'] ?? 12))
                ->connectTimeout((int) ($config['connect_timeout'] ?? 3))
                ->retry(
                    (int) ($config['retry'] ?? 2),
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
                ->asForm()
                ->post((string) ($config['translate_path'] ?? '/translate'), $this->buildPayload($config, $text, $from, $to));
        } catch (ConnectionException $exception) {
            throw new TranslationServiceException('LibreTranslate connection failed.', 'libretranslate_connection_error');
        } catch (Throwable $exception) {
            throw new TranslationServiceException('LibreTranslate request failed.', 'libretranslate_service_error');
        }

        if (! $response->successful()) {
            throw new TranslationServiceException(
                'LibreTranslate failed with HTTP ' . $response->status() . '.',
                'libretranslate_http_' . $response->status(),
                $response->status()
            );
        }

        $translated = $response->json('translatedText');
        if (! is_string($translated)) {
            throw new TranslationServiceException('LibreTranslate response is invalid.', 'libretranslate_invalid_response');
        }

        return new TranslationResult(
            translatedText: $translated,
            provider: 'libretranslate',
            meta: [
                'from' => $from,
                'to' => $to,
            ],
            durationMs: (int) round((microtime(true) - $startedAt) * 1000),
            fromCache: false
        );
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

    /**
     * @param array<string,mixed> $config
     * @return array<string,string>
     */
    private function buildPayload(array $config, string $text, string $from, string $to): array
    {
        $payload = [
            'q' => $text,
            'source' => $from,
            'target' => $to,
            'format' => 'text',
        ];

        $apiKey = trim((string) ($config['api_key'] ?? ''));
        if ($apiKey !== '') {
            $payload['api_key'] = $apiKey;
        }

        return $payload;
    }
}
