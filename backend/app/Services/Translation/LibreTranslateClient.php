<?php

namespace App\Services\Translation;

use App\Contracts\TranslationClientInterface;
use App\Services\Translation\Exceptions\TranslationClientException;
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

        $legacyTimeout = (int) config('astrobot.translation_timeout_seconds', 0);
        $configuredTimeout = (int) config('astrobot.translation.libretranslate.timeout_seconds', 8);
        $timeoutSeconds = max(1, $legacyTimeout > 0 ? $legacyTimeout : $configuredTimeout);
        $retryTimes = max(0, (int) config('astrobot.translation.libretranslate.retry_times', 1));
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
                ->retry($retryTimes, $retrySleepMs, null, false)
                ->post($endpoint, $requestPayload);
        } catch (ConnectionException $exception) {
            throw new TranslationClientException($this->provider(), 'LibreTranslate connection failed.', 0, $exception);
        } catch (Throwable $exception) {
            throw new TranslationClientException($this->provider(), 'LibreTranslate request failed.', 0, $exception);
        }

        if (! $response->successful()) {
            throw new TranslationClientException(
                $this->provider(),
                sprintf('LibreTranslate failed with HTTP %d.', $response->status())
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
}
