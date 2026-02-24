<?php

namespace App\Services\Translation;

use App\Contracts\TranslationClientInterface;
use App\Services\Translation\Exceptions\TranslationClientException;
use App\Services\Translation\Exceptions\TranslationProviderUnavailableException;
use App\Services\Translation\Exceptions\TranslationTimeoutException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Throwable;

class LibreTranslateClient implements TranslationClientInterface
{
    public function provider(): string
    {
        return 'libretranslate';
    }

    public function translate(string $text, string $targetLang, string $sourceLang = 'auto'): array
    {
        $payloadText = trim($text);
        if ($payloadText === '') {
            return [
                'text' => '',
                'provider' => $this->provider(),
                'model' => null,
                'duration_ms' => 0,
                'chars' => 0,
            ];
        }

        $startedAt = microtime(true);
        $legacyBaseUrl = trim((string) config('astrobot.translation_base_url', ''));
        $configuredBaseUrl = trim((string) config('astrobot.translation.libretranslate.url', ''));
        $baseUrl = $legacyBaseUrl !== '' ? $legacyBaseUrl : $configuredBaseUrl;

        $sharedTimeout = (int) config('astrobot.translation.timeout_sec', 12);
        $legacyTimeout = (int) config('astrobot.translation_timeout_seconds', 0);
        $configuredTimeout = (int) config('astrobot.translation.libretranslate.timeout_seconds', 8);
        $timeoutSeconds = max(1, $sharedTimeout > 0 ? $sharedTimeout : ($legacyTimeout > 0 ? $legacyTimeout : $configuredTimeout));
        $retryTimes = max(0, (int) config('astrobot.translation.max_retries', config('astrobot.translation.libretranslate.retry_times', 1)));
        $retrySleepMs = max(0, (int) config('astrobot.translation.libretranslate.retry_sleep_ms', 200));
        $apiKey = trim((string) config('astrobot.translation.libretranslate.api_key', ''));

        if ($baseUrl === '') {
            throw new TranslationClientException($this->provider(), 'LibreTranslate URL is not configured.');
        }

        $endpoint = str_ends_with(strtolower($baseUrl), '/translate')
            ? $baseUrl
            : (rtrim($baseUrl, '/') . '/translate');

        $requestPayload = [
            'q' => $payloadText,
            'source' => trim($sourceLang) !== '' ? $sourceLang : 'auto',
            'target' => trim($targetLang) !== '' ? $targetLang : 'sk',
            'format' => 'text',
        ];

        if ($apiKey !== '') {
            $requestPayload['api_key'] = $apiKey;
        }

        try {
            $response = Http::secure()
                ->acceptJson()
                ->asForm()
                ->timeout($timeoutSeconds)
                ->connectTimeout(min(5, $timeoutSeconds))
                ->retry($retryTimes, $retrySleepMs, null, false)
                ->post($endpoint, $requestPayload);
        } catch (ConnectionException $exception) {
            if ($this->isTimeoutException($exception)) {
                throw new TranslationTimeoutException($this->provider(), 'LibreTranslate request timed out.', 0, $exception);
            }

            throw new TranslationProviderUnavailableException($this->provider(), 'LibreTranslate connection failed.', 0, $exception);
        } catch (Throwable $exception) {
            if ($this->isTimeoutException($exception)) {
                throw new TranslationTimeoutException($this->provider(), 'LibreTranslate request timed out.', 0, $exception);
            }

            throw new TranslationProviderUnavailableException($this->provider(), 'LibreTranslate request failed.', 0, $exception);
        }

        if (! $response->successful()) {
            $statusCode = $response->status();
            if (in_array($statusCode, [408, 504], true)) {
                throw new TranslationTimeoutException(
                    $this->provider(),
                    sprintf('LibreTranslate request timed out (HTTP %d).', $statusCode)
                );
            }

            throw new TranslationProviderUnavailableException(
                $this->provider(),
                sprintf('LibreTranslate failed with HTTP %d.', $statusCode)
            );
        }

        $translated = $response->json('translatedText');
        if (!is_string($translated) || trim($translated) === '') {
            $translated = $response->json('translated');
        }

        $translatedText = trim((string) $translated);
        if ($translatedText === '') {
            throw new TranslationClientException($this->provider(), 'LibreTranslate response is invalid.');
        }

        return [
            'text' => $translatedText,
            'provider' => $this->provider(),
            'model' => null,
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'chars' => $this->stringLength($payloadText),
        ];
    }

    private function stringLength(string $value): int
    {
        if (function_exists('mb_strlen')) {
            return mb_strlen($value);
        }

        return strlen($value);
    }

    private function isTimeoutException(Throwable $exception): bool
    {
        $message = strtolower($exception->getMessage());

        return str_contains($message, 'timed out')
            || str_contains($message, 'timeout')
            || str_contains($message, 'curl error 28');
    }
}
