<?php

namespace App\Services\Translation\Providers;

use App\Services\Translation\Contracts\TranslationProviderInterface;
use App\Services\Translation\TranslationResult;
use App\Services\Translation\TranslationServiceException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Throwable;

class ArgosMicroserviceProvider implements TranslationProviderInterface
{
    public function translate(string $text, string $from, string $to): TranslationResult
    {
        $startedAt = microtime(true);
        $config = (array) config('translation.argos_microservice', []);

        try {
            $response = Http::baseUrl((string) ($config['base_url'] ?? 'http://127.0.0.1:8010'))
                ->timeout((int) ($config['timeout'] ?? 12))
                ->connectTimeout((int) ($config['connect_timeout'] ?? 3))
                ->retry(
                    (int) ($config['retry'] ?? 2),
                    (int) ($config['retry_sleep_ms'] ?? 250),
                    null,
                    false
                )
                ->acceptJson()
                ->withHeaders([
                    'X-Internal-Token' => (string) ($config['internal_token'] ?? ''),
                ])
                ->post((string) ($config['translate_path'] ?? '/translate'), [
                    'text' => $text,
                    'from' => $from,
                    'to' => $to,
                    'domain' => (string) ($config['default_domain'] ?? 'astronomy'),
                ]);
        } catch (ConnectionException $exception) {
            throw new TranslationServiceException('Argos translation connection failed.', 'argos_connection_error');
        } catch (Throwable $exception) {
            throw new TranslationServiceException('Argos translation request failed.', 'argos_service_error');
        }

        if (! $response->successful()) {
            throw new TranslationServiceException(
                'Argos translation failed with HTTP ' . $response->status() . '.',
                'argos_http_' . $response->status(),
                $response->status()
            );
        }

        $translated = $response->json('translated');
        if (! is_string($translated)) {
            throw new TranslationServiceException('Argos translation response is invalid.', 'argos_invalid_response');
        }

        return new TranslationResult(
            translatedText: $translated,
            provider: 'argos_microservice',
            meta: [
                'from' => $from,
                'to' => $to,
            ],
            durationMs: (int) round((microtime(true) - $startedAt) * 1000),
            fromCache: false
        );
    }
}
