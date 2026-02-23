<?php

namespace App\Services\Translation;

use App\Enums\BotTranslationStatus;
use App\Services\Bots\Contracts\BotTranslationServiceInterface;
use App\Services\Bots\Exceptions\BotTranslationException;
use App\Services\Translation\Exceptions\TranslationClientException;
use Illuminate\Support\Facades\Log;
use Throwable;

class BotTranslationService implements BotTranslationServiceInterface
{
    public function __construct(
        private readonly LibreTranslateClient $libreTranslateClient,
        private readonly OllamaTranslateClient $ollamaTranslateClient,
    ) {
    }

    public function translate(?string $title, ?string $content, string $to = 'sk'): array
    {
        $normalizedTitle = $this->normalizeTitle($title);
        $normalizedContent = $this->normalizeContent($content);
        $targetLang = $this->normalizeTargetLanguage($to);

        if ($normalizedTitle === '' && $normalizedContent === '') {
            return [
                'translated_title' => null,
                'translated_content' => null,
                'title_translated' => null,
                'content_translated' => null,
                'status' => BotTranslationStatus::SKIPPED->value,
                'meta' => [
                    'provider' => 'none',
                    'reason' => 'empty_input',
                    'target_lang' => $targetLang,
                    'duration_ms' => 0,
                    'chars' => 0,
                    'error' => null,
                    'translated_at' => now()->toIso8601String(),
                ],
            ];
        }

        if ($targetLang === 'sk' && $this->isLikelySlovak($normalizedTitle, $normalizedContent)) {
            return [
                'translated_title' => $normalizedTitle !== '' ? $normalizedTitle : null,
                'translated_content' => $normalizedContent !== '' ? $normalizedContent : null,
                'title_translated' => $normalizedTitle !== '' ? $normalizedTitle : null,
                'content_translated' => $normalizedContent !== '' ? $normalizedContent : null,
                'status' => BotTranslationStatus::SKIPPED->value,
                'meta' => [
                    'provider' => 'heuristic',
                    'reason' => 'already_slovak_heuristic',
                    'target_lang' => $targetLang,
                    'duration_ms' => 0,
                    'chars' => $this->stringLength($normalizedTitle) + $this->stringLength($normalizedContent),
                    'error' => null,
                    'translated_at' => now()->toIso8601String(),
                ],
            ];
        }

        $providers = $this->resolveProviderOrder();
        if ($providers === []) {
            return [
                'translated_title' => null,
                'translated_content' => null,
                'title_translated' => null,
                'content_translated' => null,
                'status' => BotTranslationStatus::SKIPPED->value,
                'meta' => [
                    'provider' => 'none',
                    'reason' => 'translation_not_enabled',
                    'target_lang' => $targetLang,
                    'duration_ms' => 0,
                    'chars' => $this->stringLength($normalizedTitle) + $this->stringLength($normalizedContent),
                    'error' => null,
                    'translated_at' => now()->toIso8601String(),
                ],
            ];
        }

        $sourceLang = $this->normalizeSourceLanguage((string) config('astrobot.translation.source_lang', 'en'));
        $titleResult = $normalizedTitle !== ''
            ? $this->translateLongText($normalizedTitle, $targetLang, $sourceLang, $providers)
            : null;
        $contentResult = $normalizedContent !== ''
            ? $this->translateLongText($normalizedContent, $targetLang, $sourceLang, $providers)
            : null;

        $translatedTitle = trim((string) ($titleResult['text'] ?? ''));
        $translatedContent = trim((string) ($contentResult['text'] ?? ''));

        $totalDurationMs = (int) ($titleResult['duration_ms'] ?? 0) + (int) ($contentResult['duration_ms'] ?? 0);
        $totalChars = (int) ($titleResult['chars'] ?? 0) + (int) ($contentResult['chars'] ?? 0);
        $fallbackUsed = (bool) ($titleResult['fallback_used'] ?? false) || (bool) ($contentResult['fallback_used'] ?? false);
        $provider = $this->resolveCombinedProvider(
            $titleResult['provider'] ?? null,
            $contentResult['provider'] ?? null
        );

        $model = $this->firstNonEmptyString([
            $titleResult['model'] ?? null,
            $contentResult['model'] ?? null,
        ]);

        $status = ($translatedTitle !== '' || $translatedContent !== '')
            ? BotTranslationStatus::DONE->value
            : BotTranslationStatus::SKIPPED->value;

        return [
            'translated_title' => $translatedTitle !== '' ? $translatedTitle : null,
            'translated_content' => $translatedContent !== '' ? $translatedContent : null,
            'title_translated' => $translatedTitle !== '' ? $translatedTitle : null,
            'content_translated' => $translatedContent !== '' ? $translatedContent : null,
            'status' => $status,
            'meta' => [
                'provider' => $provider,
                'provider_title' => $titleResult['provider'] ?? null,
                'provider_content' => $contentResult['provider'] ?? null,
                'model' => $model,
                'target_lang' => $targetLang,
                'duration_ms' => $totalDurationMs,
                'chars' => $totalChars,
                'fallback_used' => $fallbackUsed,
                'error' => null,
                'translated_at' => now()->toIso8601String(),
            ],
        ];
    }

    /**
     * @param list<string> $providerOrder
     * @return array{text:string,provider:string,model:?string,duration_ms:int,chars:int,fallback_used:bool}
     */
    private function translateLongText(string $text, string $targetLang, string $sourceLang, array $providerOrder): array
    {
        $chunks = $this->chunkText($text);
        $translatedChunks = [];
        $providersUsed = [];
        $totalDuration = 0;
        $chars = 0;
        $fallbackUsed = false;
        $model = null;

        foreach ($chunks as $chunk) {
            $chunkResult = $this->translateChunkWithFallback($chunk, $targetLang, $sourceLang, $providerOrder);
            $translatedChunks[] = $chunkResult['text'];
            $providersUsed[] = $chunkResult['provider'];
            $totalDuration += (int) $chunkResult['duration_ms'];
            $chars += (int) $chunkResult['chars'];
            $fallbackUsed = $fallbackUsed || (bool) ($chunkResult['fallback_used'] ?? false);

            if ($model === null) {
                $candidateModel = trim((string) ($chunkResult['model'] ?? ''));
                if ($candidateModel !== '') {
                    $model = $candidateModel;
                }
            }
        }

        $translatedText = implode("\n\n", $translatedChunks);
        $provider = $this->resolveCombinedProviderFromList($providersUsed);

        return [
            'text' => trim($translatedText),
            'provider' => $provider,
            'model' => $model,
            'duration_ms' => $totalDuration,
            'chars' => $chars,
            'fallback_used' => $fallbackUsed,
        ];
    }

    /**
     * @param list<string> $providerOrder
     * @return array{text:string,provider:string,model:?string,duration_ms:int,chars:int,fallback_used:bool}
     */
    private function translateChunkWithFallback(
        string $chunk,
        string $targetLang,
        string $sourceLang,
        array $providerOrder
    ): array
    {
        $errors = [];

        foreach ($providerOrder as $index => $providerName) {
            try {
                $client = $this->resolveClient($providerName);
                $translated = $client->translate($chunk, $targetLang, $sourceLang);

                return [
                    'text' => trim((string) ($translated['text'] ?? '')),
                    'provider' => strtolower(trim((string) ($translated['provider'] ?? $providerName))),
                    'model' => $this->nullableString($translated['model'] ?? null),
                    'duration_ms' => (int) ($translated['duration_ms'] ?? 0),
                    'chars' => (int) ($translated['chars'] ?? $this->stringLength($chunk)),
                    'fallback_used' => $index > 0,
                ];
            } catch (TranslationClientException $exception) {
                $errors[] = $this->formatProviderError($providerName, $exception->getMessage());
                Log::warning('Bot translation provider failed.', [
                    'provider' => $providerName,
                    'target_lang' => $targetLang,
                    'error' => $this->limitText($exception->getMessage(), 240),
                ]);
            } catch (Throwable $exception) {
                $errors[] = $this->formatProviderError($providerName, $exception->getMessage());
                Log::warning('Bot translation provider threw unexpected exception.', [
                    'provider' => $providerName,
                    'target_lang' => $targetLang,
                    'error' => $this->limitText($exception->getMessage(), 240),
                ]);
            }
        }

        $errorText = $errors !== [] ? implode(' | ', $errors) : 'no_provider_available';
        throw new BotTranslationException('Translation failed. ' . $this->limitText($errorText, 500));
    }

    private function resolveClient(string $providerName): LibreTranslateClient|OllamaTranslateClient
    {
        return match ($providerName) {
            'libretranslate' => $this->libreTranslateClient,
            'ollama' => $this->ollamaTranslateClient,
            default => throw new BotTranslationException(sprintf('Unsupported translation provider "%s".', $providerName)),
        };
    }

    /**
     * @return list<string>
     */
    private function resolveProviderOrder(): array
    {
        $configuredPrimary = $this->normalizeProviderName((string) config('astrobot.translation.primary', config('astrobot.translation_provider', 'libretranslate')));
        $legacyProvider = $this->normalizeProviderName((string) config('astrobot.translation_provider', ''));
        $usingLegacyPrimaryOverride = false;
        $primary = $configuredPrimary;
        if ($configuredPrimary === 'dummy' && $legacyProvider !== '' && $legacyProvider !== 'dummy') {
            $primary = $legacyProvider;
            $usingLegacyPrimaryOverride = true;
        }
        $fallbackSource = $usingLegacyPrimaryOverride
            ? (string) config('astrobot.translation_fallback_provider', '')
            : (string) config('astrobot.translation.fallback', 'ollama');
        $fallback = $this->normalizeProviderName($fallbackSource);

        if ($primary === 'dummy') {
            return [];
        }

        $providers = [];
        if ($primary !== '') {
            $providers[] = $primary;
        }
        if ($fallback !== '' && $fallback !== $primary) {
            $providers[] = $fallback;
        }

        $providers = array_values(array_unique($providers));
        $providers = array_values(array_filter($providers, static fn (string $provider): bool => $provider !== 'dummy'));

        return $providers;
    }

    private function normalizeProviderName(string $value): string
    {
        $normalized = strtolower(trim($value));

        return match ($normalized) {
            'http', 'libre', 'libretranslate' => 'libretranslate',
            'ollama' => 'ollama',
            'dummy', 'none', '' => 'dummy',
            default => $normalized,
        };
    }

    /**
     * @return list<string>
     */
    private function chunkText(string $text): array
    {
        $maxChars = max(50, (int) config('astrobot.translation.chunk_max_chars', 1800));
        $hardLimit = max($maxChars, (int) config('astrobot.translation.chunk_hard_limit_chars', 3500));

        $normalized = $this->normalizeContent($text);
        if ($normalized === '') {
            return [];
        }

        if ($this->stringLength($normalized) <= $maxChars) {
            return [$normalized];
        }

        $paragraphs = preg_split('/\R{2,}/u', $normalized) ?: [];
        $paragraphs = array_values(array_filter(array_map(
            fn (string $paragraph): string => trim($paragraph),
            $paragraphs
        ), static fn (string $paragraph): bool => $paragraph !== ''));

        if ($paragraphs === []) {
            return $this->hardSplit($normalized, $hardLimit);
        }

        $chunks = [];
        $buffer = '';

        foreach ($paragraphs as $paragraph) {
            if ($this->stringLength($paragraph) > $maxChars) {
                if ($buffer !== '') {
                    $chunks[] = $buffer;
                    $buffer = '';
                }

                foreach ($this->splitLongParagraph($paragraph, $maxChars, $hardLimit) as $piece) {
                    $chunks[] = $piece;
                }

                continue;
            }

            $candidate = $buffer === '' ? $paragraph : ($buffer . "\n\n" . $paragraph);
            if ($this->stringLength($candidate) <= $maxChars) {
                $buffer = $candidate;
                continue;
            }

            if ($buffer !== '') {
                $chunks[] = $buffer;
            }

            $buffer = $paragraph;
        }

        if ($buffer !== '') {
            $chunks[] = $buffer;
        }

        return $chunks !== [] ? $chunks : [$normalized];
    }

    /**
     * @return list<string>
     */
    private function splitLongParagraph(string $paragraph, int $maxChars, int $hardLimit): array
    {
        $sentences = preg_split('/(?<=[\.\!\?])\s+/u', trim($paragraph)) ?: [];
        $sentences = array_values(array_filter(array_map('trim', $sentences), static fn (string $sentence): bool => $sentence !== ''));
        if ($sentences === []) {
            return $this->hardSplit($paragraph, $hardLimit);
        }

        $chunks = [];
        $buffer = '';

        foreach ($sentences as $sentence) {
            if ($this->stringLength($sentence) > $maxChars) {
                if ($buffer !== '') {
                    $chunks[] = $buffer;
                    $buffer = '';
                }

                foreach ($this->hardSplit($sentence, $maxChars) as $hardChunk) {
                    $chunks[] = $hardChunk;
                }

                continue;
            }

            $candidate = $buffer === '' ? $sentence : ($buffer . ' ' . $sentence);
            if ($this->stringLength($candidate) <= $maxChars) {
                $buffer = $candidate;
                continue;
            }

            if ($buffer !== '') {
                $chunks[] = $buffer;
            }

            $buffer = $sentence;
        }

        if ($buffer !== '') {
            $chunks[] = $buffer;
        }

        return $chunks !== [] ? $chunks : $this->hardSplit($paragraph, $hardLimit);
    }

    /**
     * @return list<string>
     */
    private function hardSplit(string $text, int $limit): array
    {
        $value = trim($text);
        if ($value === '') {
            return [];
        }

        $chunks = [];
        $offset = 0;
        $length = $this->stringLength($value);
        $step = max(100, $limit);

        while ($offset < $length) {
            $piece = function_exists('mb_substr')
                ? mb_substr($value, $offset, $step)
                : substr($value, $offset, $step);
            $piece = trim((string) $piece);
            if ($piece !== '') {
                $chunks[] = $piece;
            }
            $offset += $step;
        }

        return $chunks;
    }

    private function normalizeTitle(?string $value): string
    {
        $normalized = trim((string) $value);
        if ($normalized === '') {
            return '';
        }

        $normalized = str_replace(["\r\n", "\r"], "\n", $normalized);
        $normalized = preg_replace('/\s+/u', ' ', $normalized) ?? $normalized;

        return trim($normalized);
    }

    private function normalizeContent(?string $value): string
    {
        $normalized = trim((string) $value);
        if ($normalized === '') {
            return '';
        }

        $normalized = str_replace(["\r\n", "\r"], "\n", $normalized);
        $normalized = preg_replace('/[ \t]+/u', ' ', $normalized) ?? $normalized;
        $normalized = preg_replace("/\n{3,}/u", "\n\n", $normalized) ?? $normalized;

        return trim($normalized);
    }

    private function normalizeTargetLanguage(string $value): string
    {
        $normalized = strtolower(trim($value));

        return $normalized !== '' ? $normalized : 'sk';
    }

    private function normalizeSourceLanguage(string $value): string
    {
        $normalized = strtolower(trim($value));
        return $normalized !== '' ? $normalized : 'auto';
    }

    private function resolveCombinedProvider(?string $titleProvider, ?string $contentProvider): string
    {
        $title = strtolower(trim((string) $titleProvider));
        $content = strtolower(trim((string) $contentProvider));
        if ($title !== '' && $content !== '' && $title !== $content) {
            return 'mixed';
        }

        return $title !== '' ? $title : ($content !== '' ? $content : 'unknown');
    }

    /**
     * @param list<string> $providers
     */
    private function resolveCombinedProviderFromList(array $providers): string
    {
        $clean = array_values(array_filter(array_map(
            static fn (string $provider): string => strtolower(trim($provider)),
            $providers
        ), static fn (string $provider): bool => $provider !== ''));

        if ($clean === []) {
            return 'unknown';
        }

        $unique = array_values(array_unique($clean));
        if (count($unique) === 1) {
            return $unique[0];
        }

        return 'mixed';
    }

    private function firstNonEmptyString(array $values): ?string
    {
        foreach ($values as $value) {
            $normalized = trim((string) $value);
            if ($normalized !== '') {
                return $normalized;
            }
        }

        return null;
    }

    private function isLikelySlovak(string $title, string $content): bool
    {
        $combined = trim($title . ' ' . $content);
        if ($combined === '') {
            return false;
        }

        $length = $this->stringLength($combined);
        if ($length < 40) {
            return false;
        }

        preg_match_all('/[áäčďéíĺľňóôŕšťúýžÁÄČĎÉÍĹĽŇÓÔŔŠŤÚÝŽ]/u', $combined, $matches);
        $diacriticsCount = count($matches[0] ?? []);

        return $diacriticsCount >= 5;
    }

    private function nullableString(mixed $value): ?string
    {
        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : null;
    }

    private function formatProviderError(string $provider, string $message): string
    {
        $normalizedProvider = strtolower(trim($provider));
        $normalizedMessage = $this->limitText($message, 140);

        return sprintf('%s:%s', $normalizedProvider !== '' ? $normalizedProvider : 'unknown', $normalizedMessage);
    }

    private function limitText(string $value, int $maxLength): string
    {
        if ($maxLength <= 0) {
            return '';
        }

        $normalized = trim(preg_replace('/\s+/u', ' ', $value) ?? '');
        if ($normalized === '') {
            return 'n/a';
        }

        if ($this->stringLength($normalized) <= $maxLength) {
            return $normalized;
        }

        if (function_exists('mb_substr')) {
            return mb_substr($normalized, 0, $maxLength);
        }

        return substr($normalized, 0, $maxLength);
    }

    private function stringLength(string $value): int
    {
        if (function_exists('mb_strlen')) {
            return mb_strlen($value);
        }

        return strlen($value);
    }
}
