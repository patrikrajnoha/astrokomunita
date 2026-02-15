<?php

namespace App\Services;

use App\Models\TranslationCacheEntry;
use App\Models\TranslationLog;
use App\Models\TranslationOverride;
use App\Services\Translation\Contracts\TranslationProviderInterface;
use App\Services\Translation\Grammar\Contracts\GrammarCheckerInterface;
use App\Services\Translation\Grammar\GrammarCheckException;
use App\Services\Translation\Providers\ArgosMicroserviceProvider;
use App\Services\Translation\Providers\LibreTranslateProvider;
use App\Services\Translation\TranslationResult;
use App\Services\Translation\TranslationServiceException;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class TranslationService
{
    public function __construct(
        private readonly LibreTranslateProvider $libreTranslateProvider,
        private readonly ArgosMicroserviceProvider $argosMicroserviceProvider,
        private readonly GrammarCheckerInterface $grammarChecker,
    ) {
    }

    /**
     * @throws TranslationServiceException
     */
    public function translateEnToSk(string $text, string $domain = 'astronomy'): string
    {
        $result = $this->translate($text, 'en', 'sk', $domain);
        return $result->translatedText;
    }

    /**
     * @throws TranslationServiceException
     */
    public function translate(string $text, string $from, string $to, string $domain = 'astronomy'): TranslationResult
    {
        $sourceText = $this->normalizeText($text);

        if ($sourceText === '') {
            return new TranslationResult(
                translatedText: $text,
                provider: 'none',
                meta: [
                    'from' => $from,
                    'to' => $to,
                    'domain' => $domain,
                ]
            );
        }

        $cacheKey = $this->buildCacheKey($sourceText, $from, $to);
        $sourceTextHash = hash('sha256', $sourceText);
        $preparedInput = $this->applyOverridesBeforeTranslation($sourceText, $from, $to);

        return $this->withTranslationLock($cacheKey, function () use ($cacheKey, $sourceTextHash, $preparedInput, $from, $to, $domain): TranslationResult {
            $cachedResult = $this->getCachedTranslation($cacheKey, $from, $to);
            if ($cachedResult !== null) {
                $this->recordTranslationLog(
                    provider: $cachedResult->provider,
                    status: 'cached',
                    errorCode: null,
                    durationMs: $cachedResult->durationMs,
                    from: $from,
                    to: $to,
                    sourceTextHash: $sourceTextHash
                );

                return $cachedResult;
            }

            return $this->translateWithProviders(
                preparedInput: $preparedInput,
                cacheKey: $cacheKey,
                sourceTextHash: $sourceTextHash,
                from: $from,
                to: $to,
                domain: $domain
            );
        });
    }

    private function translateWithProviders(
        string $preparedInput,
        string $cacheKey,
        string $sourceTextHash,
        string $from,
        string $to,
        string $domain
    ): TranslationResult {
        $providerNames = $this->resolveProviderChain();
        if ($providerNames === []) {
            throw new TranslationServiceException('No translation provider configured.', 'provider_not_configured');
        }

        $lastException = null;

        foreach ($providerNames as $providerName) {
            $provider = $this->resolveProvider($providerName);
            if ($provider === null) {
                continue;
            }

            $startedAt = microtime(true);

            try {
                $providerResult = $provider->translate($preparedInput, $from, $to);
                $translated = $this->applyOverridesAfterTranslation($providerResult->translatedText, $from, $to);
                $translated = $this->applyGrammarCorrections(
                    text: $translated,
                    languageTo: $to,
                    sourceTextHash: $sourceTextHash
                );
                $translated = $this->applyTargetLanguageOverrides($translated, $to);

                $durationMs = $providerResult->durationMs > 0
                    ? $providerResult->durationMs
                    : (int) round((microtime(true) - $startedAt) * 1000);

                $finalResult = new TranslationResult(
                    translatedText: $translated,
                    provider: $providerResult->provider,
                    meta: array_merge($providerResult->meta, ['domain' => $domain]),
                    durationMs: $durationMs,
                    fromCache: false
                );

                $this->storeCachedTranslation(
                    cacheKey: $cacheKey,
                    sourceTextHash: $sourceTextHash,
                    from: $from,
                    to: $to,
                    translated: $translated,
                    provider: $providerResult->provider
                );

                $this->recordTranslationLog(
                    provider: $providerResult->provider,
                    status: 'success',
                    errorCode: null,
                    durationMs: $durationMs,
                    from: $from,
                    to: $to,
                    sourceTextHash: $sourceTextHash
                );

                return $finalResult;
            } catch (TranslationServiceException $exception) {
                $durationMs = (int) round((microtime(true) - $startedAt) * 1000);
                $this->recordTranslationLog(
                    provider: $providerName,
                    status: 'failed',
                    errorCode: $exception->errorCode(),
                    durationMs: $durationMs,
                    from: $from,
                    to: $to,
                    sourceTextHash: $sourceTextHash
                );
                $lastException = $exception;
            }
        }

        if ($lastException instanceof TranslationServiceException) {
            throw $lastException;
        }

        throw new TranslationServiceException('Translation failed.', 'translation_error');
    }

    /**
     * @template T
     * @param callable():T $callback
     * @return T
     */
    private function withTranslationLock(string $cacheKey, callable $callback): mixed
    {
        try {
            return Cache::lock('translation-lock:' . $cacheKey, 10)->block(3, $callback);
        } catch (LockTimeoutException) {
            return $callback();
        } catch (Throwable) {
            return $callback();
        }
    }

    /**
     * @return array<int,string>
     */
    private function resolveProviderChain(): array
    {
        $defaultProvider = trim((string) config('translation.default_provider', 'libretranslate'));
        $fallbackProvider = trim((string) config('translation.fallback_provider', ''));

        $chain = array_values(array_filter([$defaultProvider, $fallbackProvider], static fn (string $value): bool => $value !== ''));
        return array_values(array_unique($chain));
    }

    private function resolveProvider(string $providerName): ?TranslationProviderInterface
    {
        return match ($providerName) {
            'libretranslate' => $this->libreTranslateProvider,
            'argos_microservice' => $this->argosMicroserviceProvider,
            default => null,
        };
    }

    private function normalizeText(string $value): string
    {
        $normalized = trim($value);
        if ($normalized === '') {
            return '';
        }

        $normalized = preg_replace('/\s+/u', ' ', $normalized) ?? $normalized;
        return trim($normalized);
    }

    private function buildCacheKey(string $text, string $from, string $to): string
    {
        $grammarEnabled = $this->shouldApplyGrammar($to) ? 'grammar-on' : 'grammar-off';
        $grammarProvider = strtolower(trim((string) config('translation.grammar.provider', 'none')));
        $grammarLanguage = trim((string) config('translation.grammar.languagetool.language', ''));
        $version = trim((string) config('translation.cache_key_version', 'v6'));

        return hash('sha256', implode('|', [
            $text,
            $from,
            $to,
            $version,
            $grammarEnabled,
            $grammarProvider,
            $grammarLanguage,
        ]));
    }

    private function applyOverridesBeforeTranslation(string $text, string $from, string $to): string
    {
        return $this->applyOverrides($text, $from, $to);
    }

    private function applyOverridesAfterTranslation(string $text, string $from, string $to): string
    {
        $value = $this->applyOverrides($text, $from, $to);
        return $this->applyTargetLanguageOverrides($value, $to);
    }

    private function applyTargetLanguageOverrides(string $text, string $language): string
    {
        $value = $this->applyOverrides($text, $language, $language);
        return $this->applyAstronomyPhraseFixes($value, $language);
    }

    private function applyAstronomyPhraseFixes(string $text, string $language): string
    {
        $lang = strtolower(trim($language));
        if ($lang !== 'sk' && ! str_starts_with($lang, 'sk-')) {
            return $text;
        }

        $patterns = [
            '/\bprv(?:a|á)\s+tla(?:c|č)\s+mesiaca\b/iu' => 'prvá štvrť Mesiaca',
            '/\bpolo(?:z|ž)en(?:a|á)\s+tla(?:c|č)\s+mesiaca\b/iu' => 'posledná štvrť Mesiaca',
        ];

        $value = $text;
        foreach ($patterns as $pattern => $replacement) {
            $value = preg_replace($pattern, $replacement, $value) ?? $value;
        }

        return $value;
    }

    private function applyOverrides(string $text, string $from, string $to): string
    {
        $overrides = $this->resolveOverrides($from, $to);
        if ($overrides === []) {
            return $text;
        }

        $value = $text;
        foreach ($overrides as $override) {
            $source = trim((string) $override['source_term']);
            $target = (string) $override['target_term'];
            if ($source === '') {
                continue;
            }

            $pattern = $this->buildOverridePattern(
                source: $source,
                caseSensitive: (bool) ($override['is_case_sensitive'] ?? false)
            );

            $value = preg_replace($pattern, $target, $value) ?? $value;
        }

        return $value;
    }

    private function buildOverridePattern(string $source, bool $caseSensitive): string
    {
        $escaped = preg_quote($source, '/');
        $leftBoundary = preg_match('/^[\pL\pN]/u', $source) === 1 ? '(?<![\pL\pN])' : '';
        $rightBoundary = preg_match('/[\pL\pN]$/u', $source) === 1 ? '(?![\pL\pN])' : '';
        $flags = $caseSensitive ? 'u' : 'ui';

        return '/' . $leftBoundary . $escaped . $rightBoundary . '/' . $flags;
    }

    /**
     * @return array<int,array{source_term:string,target_term:string,is_case_sensitive:bool}>
     */
    private function resolveOverrides(string $from, string $to): array
    {
        $cacheKey = "translation-overrides:{$from}:{$to}";
        $ttl = max(60, (int) config('translation.cache_ttl', 86400));
        $cacheEnabled = (bool) config('translation.cache_enabled', true);

        $loader = function () use ($from, $to): array {
            return TranslationOverride::query()
                ->where('language_from', $from)
                ->where('language_to', $to)
                ->orderByRaw('LENGTH(source_term) DESC')
                ->get(['source_term', 'target_term', 'is_case_sensitive'])
                ->map(fn (TranslationOverride $override): array => [
                    'source_term' => (string) $override->source_term,
                    'target_term' => (string) $override->target_term,
                    'is_case_sensitive' => (bool) $override->is_case_sensitive,
                ])
                ->values()
                ->all();
        };

        if (! $cacheEnabled) {
            return $loader();
        }

        return Cache::remember($cacheKey, $ttl, $loader);
    }

    private function applyGrammarCorrections(string $text, string $languageTo, string $sourceTextHash): string
    {
        if (! $this->shouldApplyGrammar($languageTo)) {
            return $text;
        }

        $provider = strtolower(trim((string) config('translation.grammar.provider', 'languagetool')));
        if ($provider !== 'languagetool') {
            return $text;
        }

        $grammarLanguage = trim((string) config('translation.grammar.languagetool.language', $languageTo));
        if ($grammarLanguage === '') {
            $grammarLanguage = $languageTo;
        }

        try {
            $result = $this->grammarChecker->correct($text, $grammarLanguage);

            if ($result->appliedFixes > 0) {
                Log::info('Translation grammar corrections applied', [
                    'provider' => $result->provider,
                    'fixes' => $result->appliedFixes,
                    'duration_ms' => $result->durationMs,
                    'language_to' => $languageTo,
                    'original_text_hash' => $sourceTextHash,
                ]);
            }

            return $result->correctedText;
        } catch (GrammarCheckException $exception) {
            Log::warning('Translation grammar check failed', [
                'provider' => $provider,
                'error_code' => $exception->errorCode(),
                'status_code' => $exception->statusCode(),
                'message' => $exception->getMessage(),
                'language_to' => $languageTo,
                'original_text_hash' => $sourceTextHash,
            ]);

            return $text;
        } catch (Throwable $exception) {
            Log::warning('Translation grammar check failed with unexpected error', [
                'provider' => $provider,
                'message' => $exception->getMessage(),
                'language_to' => $languageTo,
                'original_text_hash' => $sourceTextHash,
            ]);

            return $text;
        }
    }

    private function shouldApplyGrammar(string $languageTo): bool
    {
        if (! (bool) config('translation.grammar.enabled', false)) {
            return false;
        }

        $target = strtolower(trim($languageTo));
        if ($target === '') {
            return false;
        }

        $configured = config('translation.grammar.languages', ['sk']);
        if (is_string($configured)) {
            $configured = [$configured];
        }
        if (! is_array($configured)) {
            return false;
        }

        foreach ($configured as $language) {
            $value = strtolower(trim((string) $language));
            if ($value === '') {
                continue;
            }

            if ($target === $value) {
                return true;
            }

            if (str_starts_with($target, $value . '-')) {
                return true;
            }

            if (str_starts_with($value, $target . '-')) {
                return true;
            }
        }

        return false;
    }

    private function getCachedTranslation(string $cacheKey, string $from, string $to): ?TranslationResult
    {
        if (! (bool) config('translation.cache_enabled', true)) {
            return null;
        }

        $storeKey = "translation-result:{$cacheKey}";
        $ttl = max(60, (int) config('translation.cache_ttl', 86400));
        $cached = Cache::get($storeKey);

        if (is_array($cached) && isset($cached['translated_text']) && is_string($cached['translated_text'])) {
            return new TranslationResult(
                translatedText: $cached['translated_text'],
                provider: (string) ($cached['provider'] ?? 'cache'),
                meta: [
                    'from' => $from,
                    'to' => $to,
                ],
                durationMs: 0,
                fromCache: true
            );
        }

        try {
            $entry = TranslationCacheEntry::query()
                ->where('cache_key', $cacheKey)
                ->where('language_from', $from)
                ->where('language_to', $to)
                ->first();
        } catch (Throwable $exception) {
            Log::warning('Translation cache lookup failed', [
                'cache_key' => $cacheKey,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }

        if ($entry === null) {
            return null;
        }

        try {
            $entry->increment('hit_count');
            $entry->forceFill(['last_used_at' => now()])->save();
        } catch (Throwable $exception) {
            Log::warning('Translation cache hit update failed', [
                'cache_key' => $cacheKey,
                'error' => $exception->getMessage(),
            ]);
        }

        Cache::put($storeKey, [
            'translated_text' => $entry->translated_text,
            'provider' => $entry->provider,
        ], $ttl);

        return new TranslationResult(
            translatedText: (string) $entry->translated_text,
            provider: (string) ($entry->provider ?: 'cache'),
            meta: [
                'from' => $from,
                'to' => $to,
            ],
            durationMs: 0,
            fromCache: true
        );
    }

    private function storeCachedTranslation(
        string $cacheKey,
        string $sourceTextHash,
        string $from,
        string $to,
        string $translated,
        string $provider
    ): void {
        if (! (bool) config('translation.cache_enabled', true)) {
            return;
        }

        $ttl = max(60, (int) config('translation.cache_ttl', 86400));
        $storeKey = "translation-result:{$cacheKey}";

        try {
            TranslationCacheEntry::query()->updateOrCreate(
                ['cache_key' => $cacheKey],
                [
                    'original_text_hash' => $sourceTextHash,
                    'language_from' => $from,
                    'language_to' => $to,
                    'provider' => $provider,
                    'translated_text' => $translated,
                    'last_used_at' => now(),
                ]
            );
        } catch (Throwable $exception) {
            Log::warning('Translation cache persistence failed', [
                'cache_key' => $cacheKey,
                'error' => $exception->getMessage(),
            ]);
        }

        Cache::put($storeKey, [
            'translated_text' => $translated,
            'provider' => $provider,
        ], $ttl);
    }

    private function recordTranslationLog(
        string $provider,
        string $status,
        ?string $errorCode,
        int $durationMs,
        string $from,
        string $to,
        string $sourceTextHash
    ): void {
        try {
            TranslationLog::query()->create([
                'provider' => $provider,
                'status' => $status,
                'error_code' => $errorCode,
                'duration_ms' => max(0, $durationMs),
                'language_from' => $from,
                'language_to' => $to,
                'original_text_hash' => $sourceTextHash,
            ]);
        } catch (Throwable $exception) {
            Log::warning('Translation log persistence failed', [
                'provider' => $provider,
                'status' => $status,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
