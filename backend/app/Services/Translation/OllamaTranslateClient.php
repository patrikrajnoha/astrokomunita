<?php

namespace App\Services\Translation;

use App\Contracts\TranslationClientInterface;
use App\Services\AI\OllamaClient;
use App\Services\AI\OllamaClientException;
use App\Services\Translation\Exceptions\TranslationClientException;
use App\Services\Translation\Exceptions\TranslationProviderUnavailableException;
use App\Services\Translation\Exceptions\TranslationTimeoutException;
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
                'model' => $this->configuredModel(self::MODE_POST_EDIT),
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
                'model' => $this->configuredModel($mode),
                'duration_ms' => 0,
                'chars' => 0,
                'mode' => $mode,
            ];
        }

        $target = trim(strtolower($targetLang)) !== '' ? trim(strtolower($targetLang)) : 'sk';

        try {
            $retryAttempts = $this->resolvedRetryAttempts($mode);
            $model = $this->configuredModel($mode);
            $response = $this->ollamaClient->generate(
                prompt: $this->buildPrompt($payloadText, $target, $sourceLang, $mode, $context),
                system: $this->buildSystemPrompt($target, $sourceLang, $mode),
                options: [
                    'model' => $model,
                    'temperature' => (float) config('bots.translation.ollama.temperature', 0.15),
                    'top_p' => (float) config('bots.translation.ollama.top_p', 0.4),
                    'num_predict' => $this->resolvedNumPredict($payloadText, $mode),
                    'stop' => $this->resolvedStopSequences($mode),
                    'timeout' => $this->resolvedTimeoutSeconds($mode),
                    'connect_timeout' => $this->resolvedConnectTimeoutSeconds(),
                    'max_retries' => $retryAttempts,
                    'retry' => $retryAttempts,
                    'retry_sleep_ms' => 150,
                ]
            );
        } catch (OllamaClientException $exception) {
            if ($exception->errorCode() === 'ollama_timeout_error') {
                throw new TranslationTimeoutException($this->provider(), 'Ollama translation timed out.', 0, $exception);
            }

            throw new TranslationProviderUnavailableException($this->provider(), 'Ollama translation request failed.', 0, $exception);
        } catch (Throwable $exception) {
            if ($this->isTimeoutException($exception)) {
                throw new TranslationTimeoutException($this->provider(), 'Ollama translation timed out.', 0, $exception);
            }

            throw new TranslationProviderUnavailableException($this->provider(), 'Ollama translation failed.', 0, $exception);
        }

        $translated = $this->normalizeModelOutput((string) ($response['text'] ?? ''));
        if ($translated === '') {
            throw new TranslationClientException($this->provider(), 'Ollama translation response is empty.');
        }

        return [
            'text' => $translated,
            'provider' => $this->provider(),
            'model' => trim((string) ($response['model'] ?? $model ?? $this->configuredModel($mode))),
            'duration_ms' => (int) ($response['duration_ms'] ?? 0),
            'chars' => $this->stringLength($payloadText),
            'mode' => $mode,
        ];
    }

    private function configuredModel(string $mode = self::MODE_DIRECT_TRANSLATE): string
    {
        if ($mode === self::MODE_POST_EDIT) {
            $postEditModel = trim((string) config('bots.translation.ollama.post_edit_model', ''));
            if ($postEditModel !== '') {
                return $postEditModel;
            }
        }

        return trim((string) config('bots.translation.ollama.model', config('ai.ollama.model', 'mistral')));
    }

    private function resolvedTimeoutSeconds(string $mode): int
    {
        $sharedTimeout = max(1, (int) config('bots.translation.timeout_sec', 12));
        $configuredTimeout = max(1, (int) config('bots.translation.ollama.timeout_seconds', $sharedTimeout));
        $resolved = min($sharedTimeout, $configuredTimeout);

        if ($mode === self::MODE_POST_EDIT) {
            return max(5, (int) config('bots.translation.ollama.post_edit_timeout_seconds', $resolved));
        }

        return $resolved;
    }

    private function resolvedConnectTimeoutSeconds(): int
    {
        return max(1, (int) config('bots.translation.connect_timeout_sec', 3));
    }

    private function resolvedRetryAttempts(string $mode): int
    {
        $configured = max(0, (int) config('bots.translation.max_retries', 1));

        if ($mode === self::MODE_POST_EDIT) {
            return min($configured, (int) config('bots.translation.ollama.post_edit_retries', 0));
        }

        return $configured;
    }

    private function resolvedNumPredict(string $sourceText, string $mode): int
    {
        $configured = max(80, (int) config('bots.translation.ollama.num_predict', 280));
        $length = max(1, $this->stringLength($sourceText));

        // Post-edit should preserve full meaning and sentence coverage, so allow a larger budget.
        $multiplier = $mode === self::MODE_POST_EDIT ? 2.2 : 1.8;
        $minimum = $mode === self::MODE_POST_EDIT ? 180 : 120;
        $dynamicCap = max($minimum, (int) ceil($length * $multiplier));

        return min($configured, $dynamicCap);
    }

    /**
     * @return list<string>
     */
    private function resolvedStopSequences(string $mode): array
    {
        $baseStops = [
            "\n[TEXT]",
            "\n[ORIGINAL]",
            "\n[DRAFT_TRANSLATION]",
            "\nSOURCE_TEXT:",
            "\nORIGINAL_TEXT:",
            "\nDRAFT_TRANSLATION:",
            "\nTASK:",
        ];

        if ($mode === self::MODE_POST_EDIT) {
            return $baseStops;
        }

        return $baseStops;
    }

    private function buildSystemPrompt(string $targetLang, string $sourceLang, string $mode): string
    {
        $source = trim(strtolower($sourceLang)) !== '' && strtolower($sourceLang) !== 'auto'
            ? strtoupper(trim($sourceLang))
            : 'AUTO';
        $target = $targetLang === 'sk' ? 'Slovak (spisovna slovencina)' : strtoupper($targetLang);

        if ($mode === self::MODE_POST_EDIT) {
            return sprintf(
                'You are a factual Slovak post-editor for astronomy text. '
                . 'Rewrite the draft from %s to natural %s without changing meaning. '
                . 'Do not add, remove, infer, or guess facts. '
                . 'If information is unclear, preserve the original fact wording. '
                . 'Never connect entities only by similar names (example: Ursids is not Uranus). '
                . 'Keep names, URLs, numbers, units, mission names, and object names unchanged unless Slovak grammar requires inflection. '
                . 'Never write "studium" — the correct Slovak word is "štúdium". '
                . 'Never split a single word with spaces (e.g. "m iss ion" is wrong — write "misia"). '
                . 'Keep "White Paper" unchanged in Slovak text. '
                . 'Output only final text, no comments and no explanations.',
                $source,
                $target
            );
        }

        return sprintf(
            'You are a deterministic translation engine for astronomy content. Translate from %s to %s. '
            . 'Keep exact meaning. Do not add or remove facts. Keep URLs, numbers, units, mission names, telescope names, and organization names unchanged. '
            . 'If a term is ambiguous, keep the source term instead of guessing. '
            . 'Never connect entities only by similar names (example: Ursids is not Uranus). '
            . 'Never write "studium" — the correct Slovak word is "štúdium". '
            . 'Never split a single word with spaces (e.g. "m iss ion" is wrong — write "misia"). '
            . 'Keep "White Paper" unchanged in Slovak text. '
            . 'Write natural, fluent standard Slovak. Do not translate word-by-word and avoid anglicisms. '
            . 'Output only translated text, no labels, no markdown, no explanations.',
            $source,
            $target
        );
    }

    /**
     * @param array<string,string> $context
     */
    private function buildPrompt(string $text, string $targetLang, string $sourceLang, string $mode, array $context = []): string
    {
        $source = trim(strtolower($sourceLang)) !== '' ? strtolower(trim($sourceLang)) : 'auto';

        if ($mode === self::MODE_POST_EDIT) {
            $original = trim((string) ($context['original_text'] ?? ''));
            $draft = trim((string) ($context['draft_translation'] ?? $text));

            return "TASK: Rewrite DRAFT_TRANSLATION into natural Slovak while preserving facts.\n"
                . "Rules:\n"
                . "- Return only final edited text.\n"
                . "- Keep the same meaning as ORIGINAL_TEXT.\n"
                . "- Do not add, remove, infer, or guess facts.\n"
                . "- If uncertain, keep the original term or statement from DRAFT_TRANSLATION.\n"
                . "- Never connect entities only by similar names (example: Ursids is not Uranus).\n"
                . "- Keep names, URLs, numbers, units, and astronomy object names unchanged.\n"
                . "- Use natural Slovak wording and sentence order.\n\n"
                . "ORIGINAL_TEXT:\n<<<\n{$original}\n>>>\n\n"
                . "DRAFT_TRANSLATION:\n<<<\n{$draft}\n>>>";
        }

        return "TASK: Translate SOURCE_TEXT from {$source} to {$targetLang}.\n"
            . "Rules:\n"
            . "- Return only the final translated text in {$targetLang}.\n"
            . "- Do not output labels, brackets, XML/JSON, or explanations.\n"
            . "- Do not copy instructions or source markers.\n"
            . "- Keep names, numbers, units and URLs unchanged.\n"
            . "- Do not guess missing details; if ambiguous, keep the source term.\n"
            . "- Never connect entities only by similar names (example: Ursids is not Uranus).\n"
            . "- Use natural Slovak wording. Avoid literal translation and foreign-sounding phrases.\n"
            . "- Keep similar meaning and scope.\n\n"
            . "SOURCE_TEXT:\n<<<\n{$text}\n>>>";
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
        $normalized = str_replace("\r\n", "\n", $normalized);

        if (str_starts_with($normalized, '"') && str_ends_with($normalized, '"')) {
            $normalized = trim($normalized, '"');
        }

        $normalized = $this->truncateAtPromptLeakageMarker($normalized);
        $normalized = $this->stripPromptTaskLines($normalized);

        return trim($normalized);
    }

    private function truncateAtPromptLeakageMarker(string $value): string
    {
        $markers = [
            '[TEXT]',
            '[ORIGINAL]',
            '[DRAFT_TRANSLATION]',
            'DIRECT TRANSLATE TASK',
            'POST-EDIT TASK',
        ];

        $candidate = $value;
        foreach ($markers as $marker) {
            $pos = stripos($candidate, $marker);
            if ($pos === false || $pos <= 0) {
                continue;
            }

            $prefix = trim(substr($candidate, 0, $pos));
            if ($prefix !== '') {
                $candidate = $prefix;
                break;
            }
        }

        return $candidate;
    }

    private function stripPromptTaskLines(string $value): string
    {
        $lines = preg_split('/\R/u', $value) ?: [];
        $filtered = [];
        $skipPromptBlock = false;
        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '') {
                if (! $skipPromptBlock) {
                    $filtered[] = '';
                }
                continue;
            }

            if ($skipPromptBlock) {
                if ($trimmed === '>>>') {
                    $skipPromptBlock = false;
                }
                continue;
            }

            $lower = strtolower($trimmed);
            if (
                str_starts_with($lower, 'source_text:')
                || str_starts_with($lower, 'original_text:')
                || str_starts_with($lower, 'draft_translation:')
            ) {
                $skipPromptBlock = true;
                continue;
            }

            if (
                str_starts_with($lower, 'task:')
                || $trimmed === '<<<'
                || $trimmed === '>>>'
                || $trimmed === '[TEXT]'
                || $trimmed === '[ORIGINAL]'
                || $trimmed === '[DRAFT_TRANSLATION]'
            ) {
                continue;
            }

            $filtered[] = $trimmed;
        }

        return trim(implode("\n", $filtered));
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
