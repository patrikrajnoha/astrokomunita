<?php

namespace App\Services\Bots;

use App\Enums\BotTranslationStatus;
use App\Services\Bots\Contracts\BotTranslationServiceInterface;
use App\Services\Bots\Exceptions\BotTranslationException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Throwable;

class HttpBotTranslationService implements BotTranslationServiceInterface
{
    public function translate(?string $title, ?string $content, string $to = 'sk'): array
    {
        $normalizedTitle = $this->normalizeText($title);
        $normalizedContent = $this->normalizeText($content);

        if ($normalizedTitle === '' && $normalizedContent === '') {
            return [
                'translated_title' => null,
                'translated_content' => null,
                'title_translated' => null,
                'content_translated' => null,
                'status' => BotTranslationStatus::SKIPPED->value,
                'meta' => [
                    'provider' => 'http',
                    'reason' => 'empty_input',
                    'target_lang' => $to,
                ],
            ];
        }

        $translatedTitle = $normalizedTitle !== '' ? $this->translateText($normalizedTitle, $to) : null;
        $translatedContent = $normalizedContent !== '' ? $this->translateText($normalizedContent, $to) : null;

        return [
            'translated_title' => $translatedTitle,
            'translated_content' => $translatedContent,
            'title_translated' => $translatedTitle,
            'content_translated' => $translatedContent,
            'status' => BotTranslationStatus::DONE->value,
            'meta' => [
                'provider' => 'http',
                'target_lang' => $to,
            ],
        ];
    }

    private function translateText(string $text, string $to): string
    {
        $baseUrl = rtrim((string) config('astrobot.translation.libretranslate.url', config('astrobot.translation_base_url', 'http://127.0.0.1:5000')), '/');
        $timeoutSeconds = max(1, (int) config('astrobot.translation.timeout_sec', config('astrobot.translation_timeout_seconds', 12)));
        $translatePath = str_ends_with(strtolower($baseUrl), '/translate') ? '' : '/translate';

        if ($baseUrl === '') {
            throw new BotTranslationException('Translation endpoint is not configured.');
        }

        try {
            $response = Http::baseUrl($baseUrl)
                ->acceptJson()
                ->timeout($timeoutSeconds)
                ->retry(1, 200, null, false)
                ->asForm()
                ->post($translatePath, [
                    'q' => $text,
                    'source' => 'en',
                    'target' => $to,
                    'format' => 'text',
                ]);
        } catch (ConnectionException $exception) {
            throw new BotTranslationException('Translation request failed (network).');
        } catch (Throwable $exception) {
            throw new BotTranslationException('Translation request failed.');
        }

        if (! $response->successful()) {
            throw new BotTranslationException(sprintf('Translation request failed (HTTP %d).', $response->status()));
        }

        $translated = $response->json('translatedText');
        if (! is_string($translated) || trim($translated) === '') {
            $translated = $response->json('translated');
        }

        if (! is_string($translated) || trim($translated) === '') {
            throw new BotTranslationException('Translation response is invalid.');
        }

        return trim($translated);
    }

    private function normalizeText(?string $value): string
    {
        $normalized = trim((string) $value);
        if ($normalized === '') {
            return '';
        }

        $normalized = preg_replace('/\s+/u', ' ', $normalized) ?? $normalized;

        return trim($normalized);
    }
}
