<?php

namespace App\Services\Bots;

use App\Enums\BotTranslationStatus;
use App\Services\AI\OllamaClient;
use App\Services\AI\OllamaClientException;
use App\Services\Bots\Contracts\BotTranslationServiceInterface;
use App\Services\Bots\Exceptions\BotTranslationException;

class OllamaBotTranslationService implements BotTranslationServiceInterface
{
    public function __construct(
        private readonly OllamaClient $ollamaClient,
    ) {
    }

    public function translate(?string $title, ?string $content, string $to = 'sk'): array
    {
        $normalizedTitle = $this->normalizeText($title);
        $normalizedContent = $this->normalizeText($content);
        $targetLanguage = strtolower(trim($to));

        if ($normalizedTitle === '' && $normalizedContent === '') {
            return [
                'translated_title' => null,
                'translated_content' => null,
                'title_translated' => null,
                'content_translated' => null,
                'status' => BotTranslationStatus::SKIPPED->value,
                'meta' => [
                    'provider' => 'ollama',
                    'reason' => 'empty_input',
                    'target_lang' => $targetLanguage,
                ],
            ];
        }

        $translatedTitle = $normalizedTitle !== '' ? $this->translateText($normalizedTitle, $targetLanguage) : null;
        $translatedContent = $normalizedContent !== '' ? $this->translateText($normalizedContent, $targetLanguage) : null;

        return [
            'translated_title' => $translatedTitle,
            'translated_content' => $translatedContent,
            'title_translated' => $translatedTitle,
            'content_translated' => $translatedContent,
            'status' => BotTranslationStatus::DONE->value,
            'meta' => [
                'provider' => 'ollama',
                'target_lang' => $targetLanguage,
                'model' => trim((string) config('bots.translation_ollama_model', config('ai.ollama.model', 'mistral'))),
            ],
        ];
    }

    private function translateText(string $text, string $targetLanguage): string
    {
        $prompt = $this->buildPrompt($text, $targetLanguage);

        try {
            $response = $this->ollamaClient->generate(
                prompt: $prompt,
                system: $this->buildSystemPrompt($targetLanguage),
                options: [
                    'model' => trim((string) config('bots.translation_ollama_model', config('ai.ollama.model', 'mistral'))),
                    'temperature' => (float) config('bots.translation_ollama_temperature', 0.0),
                    'num_predict' => (int) config('bots.translation_ollama_num_predict', 700),
                    'timeout' => max(1, (int) config('bots.translation_ollama_timeout_seconds', 40)),
                ]
            );
        } catch (OllamaClientException $exception) {
            throw new BotTranslationException('Ollama translation request failed.');
        } catch (\Throwable $exception) {
            throw new BotTranslationException('Ollama translation failed.');
        }

        $translated = $this->normalizeModelOutput((string) ($response['text'] ?? ''));
        if ($translated === '') {
            throw new BotTranslationException('Ollama translation response is empty.');
        }

        return $translated;
    }

    private function buildSystemPrompt(string $targetLanguage): string
    {
        return sprintf(
            'You are a precise translation engine. Translate from English to %s. '
            . 'Keep factual meaning, names, numbers, URLs, hashtags and markdown. '
            . 'Do not add explanations. Return only the translated text.',
            $targetLanguage === 'sk' ? 'Slovak' : strtoupper($targetLanguage)
        );
    }

    private function buildPrompt(string $text, string $targetLanguage): string
    {
        return "Translate this text to {$targetLanguage}:\n\n{$text}";
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

    private function normalizeModelOutput(string $value): string
    {
        $normalized = trim($value);
        if ($normalized === '') {
            return '';
        }

        $normalized = preg_replace('/^```[a-z]*\s*/iu', '', $normalized) ?? $normalized;
        $normalized = preg_replace('/```$/u', '', $normalized) ?? $normalized;
        $normalized = preg_replace('/^\s*(translation|preklad)\s*:\s*/iu', '', $normalized) ?? $normalized;

        if (str_starts_with($normalized, '"') && str_ends_with($normalized, '"')) {
            $normalized = trim($normalized, '"');
        }

        return trim($normalized);
    }
}

