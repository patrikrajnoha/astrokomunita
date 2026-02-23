<?php

namespace App\Services\Translation;

use App\Contracts\TranslationClientInterface;
use App\Services\AI\OllamaClient;
use App\Services\AI\OllamaClientException;
use App\Services\Translation\Exceptions\TranslationClientException;
use Throwable;

class OllamaTranslateClient implements TranslationClientInterface
{
    public function __construct(
        private readonly OllamaClient $ollamaClient,
    ) {
    }

    public function provider(): string
    {
        return 'ollama';
    }

    public function translate(string $text, string $targetLang, string $sourceLang = 'auto'): array
    {
        $payloadText = trim($text);
        if ($payloadText === '') {
            return [
                'text' => '',
                'provider' => $this->provider(),
                'model' => $this->configuredModel(),
                'duration_ms' => 0,
                'chars' => 0,
            ];
        }

        $target = trim(strtolower($targetLang)) !== '' ? trim(strtolower($targetLang)) : 'sk';

        try {
            $response = $this->ollamaClient->generate(
                prompt: $this->buildPrompt($payloadText, $target),
                system: $this->buildSystemPrompt($target, $sourceLang),
                options: [
                    'model' => $this->configuredModel(),
                    'temperature' => (float) config('astrobot.translation.ollama.temperature', config('astrobot.translation_ollama_temperature', 0.0)),
                    'num_predict' => (int) config('astrobot.translation.ollama.num_predict', config('astrobot.translation_ollama_num_predict', 700)),
                    'timeout' => max(
                        1,
                        (int) config('astrobot.translation.ollama.timeout_seconds', config('astrobot.translation_ollama_timeout_seconds', 40))
                    ),
                ]
            );
        } catch (OllamaClientException $exception) {
            throw new TranslationClientException($this->provider(), 'Ollama translation request failed.', 0, $exception);
        } catch (Throwable $exception) {
            throw new TranslationClientException($this->provider(), 'Ollama translation failed.', 0, $exception);
        }

        $translated = $this->normalizeModelOutput((string) ($response['text'] ?? ''));
        if ($translated === '') {
            throw new TranslationClientException($this->provider(), 'Ollama translation response is empty.');
        }

        return [
            'text' => $translated,
            'provider' => $this->provider(),
            'model' => trim((string) ($response['model'] ?? $this->configuredModel())),
            'duration_ms' => (int) ($response['duration_ms'] ?? 0),
            'chars' => $this->stringLength($payloadText),
        ];
    }

    private function configuredModel(): string
    {
        return trim((string) config('astrobot.translation.ollama.model', config('astrobot.translation_ollama_model', config('ai.ollama.model', 'mistral'))));
    }

    private function buildSystemPrompt(string $targetLang, string $sourceLang): string
    {
        $source = trim(strtolower($sourceLang)) !== '' && strtolower($sourceLang) !== 'auto'
            ? strtoupper(trim($sourceLang))
            : 'AUTO';
        $target = $targetLang === 'sk' ? 'Slovak' : strtoupper($targetLang);

        return sprintf(
            'You are a precise translation engine. Translate from %s to %s. '
            . 'Preserve meaning, names, numbers, URLs, hashtags and markdown. '
            . 'Return only translated text.',
            $source,
            $target
        );
    }

    private function buildPrompt(string $text, string $targetLang): string
    {
        return "Translate this text to {$targetLang}:\n\n{$text}";
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

    private function stringLength(string $value): int
    {
        if (function_exists('mb_strlen')) {
            return mb_strlen($value);
        }

        return strlen($value);
    }
}

