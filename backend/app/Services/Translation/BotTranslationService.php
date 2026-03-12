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
use App\Services\Translation\Concerns\ManagesBotTranslationInternals;

class BotTranslationService implements BotTranslationServiceInterface
{
    use ManagesBotTranslationInternals;

    public function __construct(
        private readonly LibreTranslateClient $libreTranslateClient,
        private readonly OllamaTranslateClient $ollamaTranslateClient,
        private readonly TranslationOutageSimulationService $outageSimulation,
    ) {}

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
     * @param  list<string>  $providerOrder
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
     * @param  list<string>  $providerOrder
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
    ): array {
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
        throw new BotTranslationException('Translation failed. '.$this->limitText($errorText, 500));
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
     * @param  list<string>  $providerOrder
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
            ? $this->evaluateQualityFlags($originalText, $translatedText, $targetLang)
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

        if (! in_array('ollama', $providerOrder, true)) {
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
                $flags = $this->evaluateQualityFlags($originalText, $resultText, $targetLang);
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
}
