<?php

namespace App\Services\Translation;

use App\Contracts\TranslationClientInterface;
use App\Services\AI\OllamaClient;
use App\Services\AI\OllamaClientException;
use App\Services\Translation\Exceptions\TranslationClientException;
use Throwable;

class OllamaTranslateClient implements TranslationClientInterface
{
    public const MODE_DIRECT_TRANSLATE = 'direct_translate';
    public const MODE_POST_EDIT = 'post_edit';

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
        return $this->translateDirect($text, $targetLang, $sourceLang);
    }

    public function translateDirect(string $text, string $targetLang, string $sourceLang = 'auto'): array
    {
        return $this->generate(
            text: $text,
            targetLang: $targetLang,
            sourceLang: $sourceLang,
            mode: self::MODE_DIRECT_TRANSLATE,
        );
    }

    public function postEdit(
        string $originalText,
        string $draftTranslation,
        string $targetLang,
        string $sourceLang = 'auto'
    ): array {
        $normalizedOriginal = trim($originalText);
        $normalizedDraft = trim($draftTranslation);
        if ($normalizedOriginal === '' || $normalizedDraft === '') {
            return [
                'text' => $normalizedDraft,
                'provider' => $this->provider(),
                'model' => $this->configuredModel(),
                'duration_ms' => 0,
                'chars' => $this->stringLength($normalizedDraft),
                'mode' => self::MODE_POST_EDIT,
            ];
        }

        return $this->generate(
            text: $normalizedDraft,
            targetLang: $targetLang,
            sourceLang: $sourceLang,
            mode: self::MODE_POST_EDIT,
            context: [
                'original_text' => $normalizedOriginal,
                'draft_translation' => $normalizedDraft,
            ],
        );
    }

    /**
     * @param array<string,string> $context
     */
    private function generate(
        string $text,
        string $targetLang,
        string $sourceLang,
        string $mode,
        array $context = []
    ): array {
        $payloadText = trim($text);
        if ($payloadText === '') {
            return [
                'text' => '',
                'provider' => $this->provider(),
                'model' => $this->configuredModel(),
                'duration_ms' => 0,
                'chars' => 0,
                'mode' => $mode,
            ];
        }

        $target = trim(strtolower($targetLang)) !== '' ? trim(strtolower($targetLang)) : 'sk';

        try {
            $response = $this->ollamaClient->generate(
                prompt: $this->buildPrompt($payloadText, $target, $mode, $context),
                system: $this->buildSystemPrompt($target, $sourceLang, $mode),
                options: [
                    'model' => $this->configuredModel(),
                    'temperature' => (float) config('astrobot.translation.ollama.temperature', config('astrobot.translation_ollama_temperature', 0.15)),
                    'top_p' => (float) config('astrobot.translation.ollama.top_p', 0.4),
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
            'mode' => $mode,
        ];
    }

    private function configuredModel(): string
    {
        return trim((string) config('astrobot.translation.ollama.model', config('astrobot.translation_ollama_model', config('ai.ollama.model', 'mistral'))));
    }

    private function buildSystemPrompt(string $targetLang, string $sourceLang, string $mode): string
    {
        $source = trim(strtolower($sourceLang)) !== '' && strtolower($sourceLang) !== 'auto'
            ? strtoupper(trim($sourceLang))
            : 'AUTO';
        $target = $targetLang === 'sk' ? 'Slovak' : strtoupper($targetLang);

        if ($mode === self::MODE_POST_EDIT) {
            return sprintf(
                'You are a deterministic Slovak post-editor for astronomy content. '
                . 'Task: improve a draft translation from %s to %s into natural standard Slovak. '
                . 'Keep exact meaning. Do not add or remove facts. Keep URLs, numbers, units, mission names, telescope names, and organization names unchanged. '
                . 'Output only final edited text without markdown, notes, or explanations.',
                $source,
                $target
            );
        }

        return sprintf(
            'You are a deterministic translation engine. Translate from %s to %s. '
            . 'Keep exact meaning. Do not add or remove facts. Keep URLs, numbers, units, mission names, telescope names, and organization names unchanged. '
            . 'Output only translated text without markdown, notes, or explanations.',
            $source,
            $target
        );
    }

    /**
     * @param array<string,string> $context
     */
    private function buildPrompt(string $text, string $targetLang, string $mode, array $context = []): string
    {
        if ($mode === self::MODE_POST_EDIT) {
            $original = trim((string) ($context['original_text'] ?? ''));
            $draft = trim((string) ($context['draft_translation'] ?? $text));

            return "POST-EDIT TASK\n"
                . "Target language: {$targetLang}\n"
                . "Return only edited Slovak text.\n\n"
                . "[ORIGINAL]\n{$original}\n\n"
                . "[DRAFT_TRANSLATION]\n{$draft}";
        }

        return "DIRECT TRANSLATE TASK\n"
            . "Target language: {$targetLang}\n"
            . "Return only translated text.\n\n"
            . "[TEXT]\n{$text}";
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
