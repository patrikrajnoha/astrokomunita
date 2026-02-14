<?php

namespace App\Services;

use App\Services\Translation\TranslationServiceException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Throwable;

class TranslationService
{
    /**
     * @throws TranslationServiceException
     */
    public function translateEnToSk(string $text, string $domain = 'astronomy'): string
    {
        if (trim($text) === '') {
            return $text;
        }

        try {
            $response = Http::baseUrl((string) config('services.translation.base_url'))
                ->timeout((int) config('services.translation.timeout_seconds', 12))
                ->connectTimeout((int) config('services.translation.connect_timeout_seconds', 3))
                ->retry(
                    (int) config('services.translation.retries', 2),
                    (int) config('services.translation.retry_sleep_ms', 250),
                    null,
                    false
                )
                ->acceptJson()
                ->withHeaders([
                    'X-Internal-Token' => (string) config('services.translation.internal_token', ''),
                ])
                ->post((string) config('services.translation.translate_path', '/translate'), [
                    'text' => $text,
                    'from' => 'en',
                    'to' => 'sk',
                    'domain' => $domain,
                ]);
        } catch (ConnectionException $exception) {
            throw new TranslationServiceException('Translation connection failed.', 'connection_error');
        } catch (Throwable $exception) {
            throw new TranslationServiceException('Translation request failed.', 'service_error');
        }

        if (! $response->successful()) {
            throw new TranslationServiceException(
                'Translation failed with HTTP ' . $response->status() . '.',
                'http_' . $response->status(),
                $response->status()
            );
        }

        $translated = $response->json('translated');
        if (! is_string($translated)) {
            throw new TranslationServiceException('Translation response is invalid.', 'invalid_response');
        }

        return $translated;
    }
}
