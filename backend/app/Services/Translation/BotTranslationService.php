<?php

namespace App\Services\Translation;

use App\Enums\BotTranslationStatus;
use App\Services\Bots\Contracts\BotTranslationServiceInterface;
use App\Services\Bots\Exceptions\BotTranslationException;
use App\Services\Translation\Exceptions\TranslationClientException;
use App\Services\Translation\Exceptions\TranslationProviderUnavailableException;
use App\Services\Translation\Exceptions\TranslationTimeoutException;
use Illuminate\Support\Facades\Log;
use Throwable;

class BotTranslationService implements BotTranslationServiceInterface
{
    public function __construct(
        private readonly LibreTranslateClient $libreTranslateClient,
        private readonly OllamaTranslateClient $ollamaTranslateClient,
        private readonly TranslationOutageSimulationService $outageSimulation,
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
                    'mode' => 'none',
                    'provider_chain' => [],
                    'quality_flags' => [],
                    'quality_retry_count' => 0,
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
                    'mode' => 'none',
                    'provider_chain' => [],
                    'quality_flags' => [],
                    'quality_retry_count' => 0,
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
                    'mode' => 'none',
                    'provider_chain' => [],
                    'quality_flags' => [],
                    'quality_retry_count' => 0,
                ],
            ];
        }

        $sourceLang = $this->normalizeSourceLanguage((string) config('bots.translation.source_lang', 'en'));
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
        $providerChain = $this->mergeStringLists(
            $titleResult['provider_chain'] ?? [],
            $contentResult['provider_chain'] ?? []
        );
        $qualityFlags = $this->mergeStringLists(
            $titleResult['quality_flags'] ?? [],
            $contentResult['quality_flags'] ?? []
        );
        $qualityRetryCount = (int) ($titleResult['quality_retry_count'] ?? 0) + (int) ($contentResult['quality_retry_count'] ?? 0);
        $mode = $this->resolveCombinedMode(
            $titleResult['mode'] ?? null,
            $contentResult['mode'] ?? null
        );

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
                'provider_chain' => $providerChain,
                'mode' => $mode,
                'model' => $model,
                'target_lang' => $targetLang,
                'duration_ms' => $totalDurationMs,
                'chars' => $totalChars,
                'fallback_used' => $fallbackUsed,
                'quality_flags' => $qualityFlags,
                'quality_retry_count' => $qualityRetryCount,
                'error' => null,
                'translated_at' => now()->toIso8601String(),
            ],
        ];
    }

    /**
     * @param list<string> $providerOrder
     * @return array{
     *   text:string,
     *   provider:string,
     *   model:?string,
     *   duration_ms:int,
     *   chars:int,
     *   fallback_used:bool,
     *   provider_chain:list<string>,
     *   mode:string,
     *   quality_flags:list<string>,
     *   quality_retry_count:int
     * }
     */
    private function translateLongText(string $text, string $targetLang, string $sourceLang, array $providerOrder): array
    {
        $protection = $this->protectTermsInText($text);
        $chunks = $this->chunkText($protection['text']);
        $translatedChunks = [];
        $providersUsed = [];
        $allModes = [];
        $allQualityFlags = [];
        $allProviderChain = [];
        $totalQualityRetries = 0;
        $totalDuration = 0;
        $chars = 0;
        $fallbackUsed = false;
        $model = null;

        foreach ($chunks as $chunk) {
            $chunkResult = $this->translateChunkWithFallback($chunk, $targetLang, $sourceLang, $providerOrder);
            $translatedChunks[] = $chunkResult['text'];
            $providersUsed[] = $chunkResult['provider'];
            $allModes[] = $chunkResult['mode'];
            $allQualityFlags = $this->mergeStringLists($allQualityFlags, $chunkResult['quality_flags']);
            $allProviderChain = $this->mergeStringLists($allProviderChain, $chunkResult['provider_chain']);
            $totalQualityRetries += (int) $chunkResult['quality_retry_count'];
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
        $translatedText = $this->restoreProtectedTerms($translatedText, $protection['map']);
        $translatedText = $this->applyTerminologyMap($translatedText);
        $provider = $this->resolveCombinedProviderFromList($providersUsed);
        $mode = $this->resolveModeFromList($allModes);

        return [
            'text' => trim($translatedText),
            'provider' => $provider,
            'model' => $model,
            'duration_ms' => $totalDuration,
            'chars' => $chars,
            'fallback_used' => $fallbackUsed,
            'provider_chain' => $allProviderChain,
            'mode' => $mode,
            'quality_flags' => $allQualityFlags,
            'quality_retry_count' => $totalQualityRetries,
        ];
    }

    /**
     * @param list<string> $providerOrder
     * @return array{
     *   text:string,
     *   provider:string,
     *   model:?string,
     *   duration_ms:int,
     *   chars:int,
     *   fallback_used:bool,
     *   provider_chain:list<string>,
     *   mode:string,
     *   quality_flags:list<string>,
     *   quality_retry_count:int
     * }
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
                $translated = $this->translateUsingProvider($providerName, $chunk, $targetLang, $sourceLang);
                $translatedText = trim((string) ($translated['text'] ?? ''));
                $translatedProvider = strtolower(trim((string) ($translated['provider'] ?? $providerName)));
                $translatedModel = $this->nullableString($translated['model'] ?? null);
                $translatedDuration = (int) ($translated['duration_ms'] ?? 0);
                $providerChain = [$translatedProvider];
                $mode = $translated['mode'] ?? ($translatedProvider === 'ollama' ? 'ollama_direct' : 'lt_only');

                if ($providerName === 'libretranslate' && $this->shouldUsePostEdit($targetLang, $providerOrder)) {
                    try {
                        $postEdit = $this->ollamaTranslateClient->postEdit($chunk, $translatedText, $targetLang, $sourceLang);
                        $postEditText = trim((string) ($postEdit['text'] ?? ''));
                        if ($postEditText !== '') {
                            $translatedText = $postEditText;
                            $translatedProvider = 'ollama_postedit';
                            $translatedModel = $this->nullableString($postEdit['model'] ?? $translatedModel);
                            $translatedDuration += (int) ($postEdit['duration_ms'] ?? 0);
                            $providerChain[] = 'ollama_postedit';
                            $mode = 'lt_ollama_postedit';
                        }
                    } catch (Throwable $exception) {
                        Log::warning('Bot translation post-edit skipped; Ollama unavailable.', [
                            'provider' => 'ollama',
                            'target_lang' => $targetLang,
                            'error' => $this->limitText($exception->getMessage(), 240),
                        ]);
                    }
                }

                $quality = $this->applyQualityRetryIfNeeded(
                    originalText: $chunk,
                    translatedText: $translatedText,
                    targetLang: $targetLang,
                    sourceLang: $sourceLang,
                    providerOrder: $providerOrder
                );

                $translatedText = $quality['text'];
                $translatedProvider = $quality['provider'] ?? $translatedProvider;
                $translatedModel = $quality['model'] ?? $translatedModel;
                $translatedDuration += (int) ($quality['duration_ms'] ?? 0);
                $providerChain = $this->mergeStringLists($providerChain, $quality['provider_chain'] ?? []);
                $mode = $quality['mode'] ?? $mode;

                return [
                    'text' => $translatedText,
                    'provider' => $translatedProvider,
                    'model' => $translatedModel,
                    'duration_ms' => $translatedDuration,
                    'chars' => (int) ($translated['chars'] ?? $this->stringLength($chunk)),
                    'fallback_used' => $index > 0,
                    'provider_chain' => $providerChain,
                    'mode' => $mode,
                    'quality_flags' => $quality['quality_flags'] ?? [],
                    'quality_retry_count' => (int) ($quality['quality_retry_count'] ?? 0),
                ];
            } catch (TranslationClientException $exception) {
                $errorType = $this->resolveTranslationErrorType($exception);
                $errors[] = $this->formatProviderError($providerName, $exception->getMessage());
                Log::warning('Bot translation provider failed.', [
                    'provider' => $providerName,
                    'timeout_sec' => $this->providerTimeoutSeconds($providerName),
                    'error_type' => $errorType,
                    'target_lang' => $targetLang,
                    'error' => $this->limitText($exception->getMessage(), 240),
                ]);
            } catch (Throwable $exception) {
                $errors[] = $this->formatProviderError($providerName, $exception->getMessage());
                Log::warning('Bot translation provider threw unexpected exception.', [
                    'provider' => $providerName,
                    'timeout_sec' => $this->providerTimeoutSeconds($providerName),
                    'error_type' => 'unhandled_exception',
                    'target_lang' => $targetLang,
                    'error' => $this->limitText($exception->getMessage(), 240),
                ]);
            }
        }

        $errorText = $errors !== [] ? implode(' | ', $errors) : 'no_provider_available';
        throw new BotTranslationException('Translation failed. ' . $this->limitText($errorText, 500));
    }

    /**
     * @return array{text:string,provider:string,model:?string,duration_ms:int,chars:int,mode:string}
     */
    private function translateUsingProvider(
        string $providerName,
        string $chunk,
        string $targetLang,
        string $sourceLang
    ): array {
        if ($this->outageSimulation->shouldSimulateFor($providerName)) {
            throw new TranslationProviderUnavailableException(
                $providerName,
                sprintf('Simulated outage for provider "%s".', $providerName)
            );
        }

        return match ($providerName) {
            'libretranslate' => $this->libreTranslateClient->translate($chunk, $targetLang, $sourceLang) + ['mode' => 'lt_only'],
            'ollama' => $this->ollamaTranslateClient->translateDirect($chunk, $targetLang, $sourceLang) + ['mode' => 'ollama_direct'],
            default => throw new BotTranslationException(sprintf('Unsupported translation provider "%s".', $providerName)),
        };
    }

    /**
     * @param list<string> $providerOrder
     * @return array{
     *   text:string,
     *   provider:?string,
     *   model:?string,
     *   duration_ms:int,
     *   provider_chain:list<string>,
     *   mode:?string,
     *   quality_flags:list<string>,
     *   quality_retry_count:int
     * }
     */
    private function applyQualityRetryIfNeeded(
        string $originalText,
        string $translatedText,
        string $targetLang,
        string $sourceLang,
        array $providerOrder
    ): array {
        $qualityEnabled = (bool) config('bots.translation.quality.enabled', true);
        $flags = $qualityEnabled
            ? $this->evaluateQualityFlags($originalText, $translatedText)
            : [];

        if ($flags === []) {
            return [
                'text' => $translatedText,
                'provider' => null,
                'model' => null,
                'duration_ms' => 0,
                'provider_chain' => [],
                'mode' => null,
                'quality_flags' => [],
                'quality_retry_count' => 0,
            ];
        }

        if (!in_array('ollama', $providerOrder, true)) {
            return [
                'text' => $translatedText,
                'provider' => null,
                'model' => null,
                'duration_ms' => 0,
                'provider_chain' => [],
                'mode' => null,
                'quality_flags' => $flags,
                'quality_retry_count' => 0,
            ];
        }

        $maxRetries = max(0, (int) config('bots.translation.quality.max_retries', 1));
        if ($maxRetries === 0) {
            return [
                'text' => $translatedText,
                'provider' => null,
                'model' => null,
                'duration_ms' => 0,
                'provider_chain' => [],
                'mode' => null,
                'quality_flags' => $flags,
                'quality_retry_count' => 0,
            ];
        }

        $resultText = $translatedText;
        $durationMs = 0;
        $provider = null;
        $model = null;
        $providerChain = [];
        $mode = null;
        $retryCount = 0;

        while ($flags !== [] && $retryCount < $maxRetries) {
            try {
                $retry = $this->ollamaTranslateClient->translateDirect($originalText, $targetLang, $sourceLang);
                $retryText = trim((string) ($retry['text'] ?? ''));
                if ($retryText === '') {
                    break;
                }

                $resultText = $retryText;
                $provider = 'ollama';
                $model = $this->nullableString($retry['model'] ?? null);
                $durationMs += (int) ($retry['duration_ms'] ?? 0);
                $providerChain[] = 'ollama_direct';
                $mode = 'ollama_direct';
                $retryCount++;
                $flags = $this->evaluateQualityFlags($originalText, $resultText);
            } catch (Throwable $exception) {
                Log::warning('Bot translation quality retry failed.', [
                    'provider' => 'ollama',
                    'target_lang' => $targetLang,
                    'error' => $this->limitText($exception->getMessage(), 240),
                ]);
                break;
            }
        }

        return [
            'text' => $resultText,
            'provider' => $provider,
            'model' => $model,
            'duration_ms' => $durationMs,
            'provider_chain' => $providerChain,
            'mode' => $mode,
            'quality_flags' => $flags,
            'quality_retry_count' => $retryCount,
        ];
    }

    /**
     * @return list<string>
     */
    private function evaluateQualityFlags(string $originalText, string $translatedText): array
    {
        $flags = [];
        $original = trim($originalText);
        $translated = trim($translatedText);

        if ($translated === '') {
            $flags[] = 'empty_result';
            return $flags;
        }

        $originalLength = max(1, $this->stringLength($original));
        $translatedLength = $this->stringLength($translated);
        $minLengthRatio = max(0.1, (float) config('bots.translation.quality.min_length_ratio', 0.70));
        if (($translatedLength / $originalLength) < $minLengthRatio) {
            $flags[] = 'too_short';
        }

        if ($this->normalizedForComparison($translated) === $this->normalizedForComparison($original)) {
            $flags[] = 'identical';
        }

        $englishRatio = $this->englishTokenRatio($translated);
        $maxEnglishRatio = max(0.0, min(1.0, (float) config('bots.translation.quality.max_english_ratio', 0.20)));
        if ($englishRatio > $maxEnglishRatio) {
            $flags[] = 'too_much_en';
        }

        return array_values(array_unique($flags));
    }

    private function normalizedForComparison(string $value): string
    {
        return strtolower(trim(preg_replace('/\s+/u', ' ', $value) ?? ''));
    }

    private function englishTokenRatio(string $text): float
    {
        $matches = [];
        preg_match_all('/\b[\pL]{2,}\b/u', $text, $matches);
        $tokens = $matches[0] ?? [];
        $total = count($tokens);
        if ($total === 0) {
            return 0.0;
        }

        $englishCount = 0;
        foreach ($tokens as $token) {
            $tokenText = trim((string) $token);
            if ($tokenText === '') {
                continue;
            }
            if (!preg_match('/^[a-z]{3,}$/i', $tokenText)) {
                continue;
            }
            if (strtoupper($tokenText) === $tokenText && strlen($tokenText) <= 6) {
                continue;
            }
            $englishCount++;
        }

        return $englishCount / $total;
    }

    /**
     * @param list<string> $providerOrder
     */
    private function shouldUsePostEdit(string $targetLang, array $providerOrder): bool
    {
        if (strtolower(trim($targetLang)) !== 'sk') {
            return false;
        }
        if (!(bool) config('bots.translation.post_edit.enabled', true)) {
            return false;
        }
        if (!in_array('ollama', $providerOrder, true)) {
            return false;
        }

        $requireFallback = (bool) config('bots.translation.post_edit.require_ollama_fallback', true);
        if (!$requireFallback) {
            return true;
        }

        $configuredFallback = $this->normalizeProviderName((string) config('bots.translation.fallback', 'ollama'));
        return $configuredFallback === 'ollama';
    }

    /**
     * @return array{text:string,map:array<string,string>}
     */
    private function protectTermsInText(string $text): array
    {
        $protectedTerms = config('bots.translation.protected_terms', []);
        if (!is_array($protectedTerms) || $protectedTerms === []) {
            return ['text' => $text, 'map' => []];
        }

        $terms = array_values(array_filter(array_map(
            static fn (mixed $term): string => trim((string) $term),
            $protectedTerms
        ), static fn (string $term): bool => $term !== ''));
        if ($terms === []) {
            return ['text' => $text, 'map' => []];
        }

        usort($terms, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));

        $protectedText = $text;
        $map = [];
        $counter = 0;

        foreach ($terms as $term) {
            $pattern = '/' . preg_quote($term, '/') . '/iu';
            $protectedText = (string) preg_replace_callback(
                $pattern,
                function (array $matches) use (&$map, &$counter): string {
                    $counter++;
                    $placeholder = sprintf('__AKPH_%d__', $counter);
                    $map[$placeholder] = (string) ($matches[0] ?? '');
                    return $placeholder;
                },
                $protectedText
            );
        }

        return ['text' => $protectedText, 'map' => $map];
    }

    /**
     * @param array<string,string> $map
     */
    private function restoreProtectedTerms(string $text, array $map): string
    {
        if ($map === []) {
            return $text;
        }

        $restored = str_replace(array_keys($map), array_values($map), $text);

        foreach ($map as $placeholder => $original) {
            if (!is_string($placeholder) || !is_string($original) || $original === '') {
                continue;
            }

            if (preg_match('/^__AKPH_(\d+)__$/i', $placeholder, $matches) !== 1) {
                continue;
            }

            $id = (string) ($matches[1] ?? '');
            if ($id === '') {
                continue;
            }

            // Some providers normalize placeholders into "AKPH 1" or legacy "TERM 1".
            $variantPattern = '/\b(?:AKPH|TERM)[\s_]*' . preg_quote($id, '/') . '\b/iu';
            $restored = (string) preg_replace($variantPattern, $original, $restored);
        }

        return $restored;
    }

    private function applyTerminologyMap(string $text): string
    {
        $map = config('bots.translation.terminology_map', []);
        if (!is_array($map) || $map === []) {
            return $text;
        }

        $result = $text;
        foreach ($map as $from => $to) {
            $source = trim((string) $from);
            $target = trim((string) $to);
            if ($source === '' || $target === '') {
                continue;
            }

            $pattern = '/(?<!\pL)' . preg_quote($source, '/') . '(?!\pL)/iu';
            $result = (string) preg_replace($pattern, $target, $result);
        }

        return $result;
    }

    /**
     * @return list<string>
     */
    private function resolveProviderOrder(): array
    {
        $configuredPrimary = $this->normalizeProviderName((string) config('bots.translation.primary', config('bots.translation_provider', 'libretranslate')));
        $legacyProvider = $this->normalizeProviderName((string) config('bots.translation_provider', ''));
        $usingLegacyPrimaryOverride = false;
        $primary = $configuredPrimary;
        if ($configuredPrimary === 'dummy' && $legacyProvider !== '' && $legacyProvider !== 'dummy') {
            $primary = $legacyProvider;
            $usingLegacyPrimaryOverride = true;
        }
        $fallbackSource = $usingLegacyPrimaryOverride
            ? (string) config('bots.translation_fallback_provider', '')
            : (string) config('bots.translation.fallback', 'ollama');
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
        $maxChars = max(50, (int) config('bots.translation.chunk_max_chars', 1800));
        $hardLimit = max($maxChars, (int) config('bots.translation.chunk_hard_limit_chars', 3500));

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

    private function resolveCombinedMode(?string $titleMode, ?string $contentMode): string
    {
        $title = strtolower(trim((string) $titleMode));
        $content = strtolower(trim((string) $contentMode));
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

    /**
     * @param list<string> $modes
     */
    private function resolveModeFromList(array $modes): string
    {
        $clean = array_values(array_filter(array_map(
            static fn (string $mode): string => strtolower(trim($mode)),
            $modes
        ), static fn (string $mode): bool => $mode !== ''));

        if ($clean === []) {
            return 'unknown';
        }

        $unique = array_values(array_unique($clean));
        if (count($unique) === 1) {
            return $unique[0];
        }

        return 'mixed';
    }

    /**
     * @param list<string> $a
     * @param list<string> $b
     * @return list<string>
     */
    private function mergeStringLists(array $a, array $b): array
    {
        $merged = array_merge($a, $b);
        $clean = array_values(array_filter(array_map(
            static fn (mixed $value): string => strtolower(trim((string) $value)),
            $merged
        ), static fn (string $value): bool => $value !== ''));

        return array_values(array_unique($clean));
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

        $normalized = function_exists('mb_strtolower')
            ? mb_strtolower($combined, 'UTF-8')
            : strtolower($combined);

        preg_match_all('/[ĂˇĂ¤ÄŤÄŹĂ©Ă­ÄşÄľĹĂłĂ´Ĺ•ĹˇĹĄĂşĂ˝Ĺľ]/u', $normalized, $diacriticsMatches);
        $diacriticsCount = count($diacriticsMatches[0] ?? []);
        if ($diacriticsCount < 3) {
            return false;
        }

        $slovakHints = [
            ' je ',
            ' sa ',
            ' v ',
            ' na ',
            ' Ĺľe ',
            ' pre ',
            ' ako ',
            ' ktorĂ˝ ',
            ' ktorĂˇ ',
            ' ktorĂ© ',
            ' sĂş ',
            ' sme ',
            ' bol ',
            ' bola ',
        ];
        $englishHintsPattern = '/\b(the|and|with|for|from|this|that|are|was|were|mission|space|telescope)\b/u';

        $slovakHintCount = 0;
        $padded = ' ' . $normalized . ' ';
        foreach ($slovakHints as $hint) {
            if (str_contains($padded, $hint)) {
                $slovakHintCount++;
            }
        }

        preg_match_all($englishHintsPattern, $normalized, $englishMatches);
        $englishHintCount = count($englishMatches[0] ?? []);

        if ($slovakHintCount === 0) {
            return false;
        }

        return $englishHintCount <= ($slovakHintCount * 2);
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

    private function resolveTranslationErrorType(TranslationClientException $exception): string
    {
        if ($exception instanceof TranslationTimeoutException) {
            return 'translation_timeout';
        }

        if ($exception instanceof TranslationProviderUnavailableException) {
            return 'provider_unavailable';
        }

        return 'translation_error';
    }

    private function providerTimeoutSeconds(string $providerName): int
    {
        $shared = max(1, (int) config('bots.translation.timeout_sec', 12));

        return match (strtolower(trim($providerName))) {
            'ollama' => min($shared, max(1, (int) config('bots.translation.ollama.timeout_seconds', $shared))),
            default => max(1, (int) config('bots.translation.libretranslate.timeout_seconds', $shared)),
        };
    }
}
