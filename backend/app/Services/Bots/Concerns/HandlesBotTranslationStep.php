<?php

namespace App\Services\Bots\Concerns;

use App\Enums\BotPublishStatus;
use App\Enums\BotTranslationStatus;
use App\Models\BotItem;
use App\Models\BotSource;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Handles the translation pipeline step executed during a bot run.
 *
 * Depends on methods from HandlesBotRunnerInternals (resolved via the host class).
 */
trait HandlesBotTranslationStep
{
    /**
     * @param array<string,mixed> $stats
     */
    private function applyTranslationStep(BotSource $source, array &$stats, string $runContext, int $runId): void
    {
        $items = BotItem::query()
            ->where('source_id', $source->id)
            ->where(function ($query) use ($runId): void {
                $query
                    ->where('run_id', $runId)
                    ->orWhere('meta->last_seen_run_id', $runId);
            })
            ->whereIn('publish_status', [
                BotPublishStatus::PENDING->value,
                BotPublishStatus::PUBLISHED->value,
            ])
            ->get();

        $sourceKey = strtolower(trim((string) $source->key));

        foreach ($items as $item) {
            $this->stampItemRunContext($item, $runContext);
            $currentStatus = $item->translation_status?->value ?? (string) $item->translation_status;
            $meta = is_array($item->meta) ? $item->meta : [];
            $legacyPlaceholder = $currentStatus === BotTranslationStatus::DONE->value
                && !$this->hasAnyTranslatedText($item)
                && $this->isLegacyTranslationPlaceholder($meta);
            $legacySkippedPlaceholder = $currentStatus === BotTranslationStatus::SKIPPED->value
                && !$this->hasAnyTranslatedText($item)
                && $this->isLegacyTranslationPlaceholder($meta);
            $heuristicSkippedRetry = $currentStatus === BotTranslationStatus::SKIPPED->value
                && !$this->hasAnyTranslatedText($item)
                && $this->isHeuristicSkippedEnglishSource($item, $meta);

            if (!in_array($currentStatus, ['', BotTranslationStatus::PENDING->value, BotTranslationStatus::FAILED->value, null], true)
                && !$legacyPlaceholder
                && !$legacySkippedPlaceholder
                && !$heuristicSkippedRetry) {
                continue;
            }

            $langOriginal = strtolower(trim((string) $item->lang_original));
            if ($langOriginal === 'sk' || str_starts_with($langOriginal, 'sk-')) {
                $meta = is_array($item->meta) ? $item->meta : [];
                $this->markTranslationSkipped($item, $meta, $runContext, 'source_lang_sk', 'source_lang');
                if (config('app.debug')) {
                    Log::debug('Bot translation skipped in runner.', [
                        'source_key' => $sourceKey,
                        'stable_key' => (string) $item->stable_key,
                        'bot_run_id' => $runId,
                        'translation_status' => BotTranslationStatus::SKIPPED->value,
                        'provider' => 'source_lang',
                        'reason' => 'source_lang_sk',
                        'origin_title_hash' => $this->shortHash((string) $item->title),
                        'origin_body_hash' => $this->shortHash((string) ($item->content ?: $item->summary ?: '')),
                        'translated_title_hash' => null,
                        'translated_body_hash' => null,
                    ]);
                }
                continue;
            }

            $title = trim((string) $item->title);
            $content = trim((string) ($item->content ?: $item->summary ?: ''));

            if ($title === '' && $content === '') {
                $meta = is_array($item->meta) ? $item->meta : [];
                $this->markTranslationSkipped($item, $meta, $runContext, 'empty_input', 'none');
                if (config('app.debug')) {
                    Log::debug('Bot translation skipped in runner.', [
                        'source_key' => $sourceKey,
                        'stable_key' => (string) $item->stable_key,
                        'bot_run_id' => $runId,
                        'translation_status' => BotTranslationStatus::SKIPPED->value,
                        'provider' => 'none',
                        'reason' => 'empty_input',
                        'origin_title_hash' => $this->shortHash($title),
                        'origin_body_hash' => $this->shortHash($content),
                        'translated_title_hash' => null,
                        'translated_body_hash' => null,
                    ]);
                }
                continue;
            }

            $isEnglishSource = $langOriginal === 'en' || str_starts_with($langOriginal, 'en-');
            if (!$isEnglishSource && $this->isLikelySlovakText($title, $content)) {
                $meta = is_array($item->meta) ? $item->meta : [];
                $this->markTranslationSkipped($item, $meta, $runContext, 'already_slovak_heuristic', 'heuristic');
                if (config('app.debug')) {
                    Log::debug('Bot translation skipped in runner.', [
                        'source_key' => $sourceKey,
                        'stable_key' => (string) $item->stable_key,
                        'bot_run_id' => $runId,
                        'translation_status' => BotTranslationStatus::SKIPPED->value,
                        'provider' => 'heuristic',
                        'reason' => 'already_slovak_heuristic',
                        'origin_title_hash' => $this->shortHash($title),
                        'origin_body_hash' => $this->shortHash($content),
                        'translated_title_hash' => $this->shortHash($title),
                        'translated_body_hash' => $this->shortHash($content),
                    ]);
                }
                continue;
            }

            $cacheKey = sha1('sk|' . $title . '|' . $content);
            $existingCacheKey = trim((string) ($meta['translation_cache_key'] ?? ''));

            if ($existingCacheKey !== '' && hash_equals($existingCacheKey, $cacheKey)) {
                if ($this->hasAnyTranslatedText($item)) {
                    $meta['run_context'] = $runContext;
                    $item->forceFill([
                        'translation_status' => BotTranslationStatus::DONE->value,
                        'translation_error' => null,
                        'translation_provider' => $this->nullableString((string) data_get($meta, 'translation.provider')),
                        'translated_at' => $item->translated_at ?? now(),
                        'meta' => $meta,
                    ])->save();
                    $stats['translation_done_count'] = (int) ($stats['translation_done_count'] ?? 0) + 1;
                }
                if (config('app.debug')) {
                    Log::debug('Bot translation cache hit in runner.', [
                        'source_key' => $sourceKey,
                        'stable_key' => (string) $item->stable_key,
                        'bot_run_id' => $runId,
                        'translation_status' => $item->translation_status?->value ?? (string) $item->translation_status,
                        'provider' => $item->translation_provider,
                        'reason' => 'cache_hit',
                        'origin_title_hash' => $this->shortHash($title),
                        'origin_body_hash' => $this->shortHash($content),
                        'translated_title_hash' => $this->shortHash((string) $item->title_translated),
                        'translated_body_hash' => $this->shortHash((string) $item->content_translated),
                    ]);
                }

                if ($this->hasAnyTranslatedText($item)) {
                    continue;
                }

                // Legacy rows may carry cache keys from dummy/no-op translation without any translated payload.
                unset($meta['translation_cache_key']);
            }

            try {
                if (config('app.debug')) {
                    Log::debug('Bot translation start in runner.', [
                        'source_key' => $sourceKey,
                        'stable_key' => (string) $item->stable_key,
                        'bot_run_id' => $runId,
                        'translation_status' => BotTranslationStatus::PENDING->value,
                        'provider' => $this->resolveConfiguredTranslationProvider(),
                        'origin_title_hash' => $this->shortHash($title),
                        'origin_body_hash' => $this->shortHash($content),
                    ]);
                }

                $result = $this->translationService->translate($title, $content, 'sk');
                $meta['translation_cache_key'] = $cacheKey;
                unset($meta['translation_error']);

                $translationMeta = is_array($result['meta'] ?? null) ? $result['meta'] : null;
                if ($translationMeta !== null) {
                    $meta['translation'] = $translationMeta;
                }
                $meta['run_context'] = $runContext;
                $translatedTitle = $this->nullableString($result['translated_title'] ?? $result['title_translated'] ?? null);
                $translatedContent = $this->nullableString($result['translated_content'] ?? $result['content_translated'] ?? null);
                $status = strtolower(trim((string) ($result['status'] ?? BotTranslationStatus::DONE->value)));
                if (!in_array($status, [
                    BotTranslationStatus::DONE->value,
                    BotTranslationStatus::SKIPPED->value,
                    BotTranslationStatus::FAILED->value,
                ], true)) {
                    $status = BotTranslationStatus::DONE->value;
                }

                $translationProvider = $this->resolveTranslationProvider($translationMeta, $item->translation_provider);
                $translationError = $this->nullableString(data_get($translationMeta, 'error'));
                $translatedAt = $this->resolveTranslatedAt(data_get($translationMeta, 'translated_at')) ?? now();

                $item->forceFill([
                    'title_translated' => $translatedTitle,
                    'content_translated' => $translatedContent,
                    'translation_status' => $status,
                    'translation_error' => $translationError,
                    'translation_provider' => $translationProvider,
                    'translated_at' => $translatedAt,
                    'meta' => $meta,
                ])->save();

                if ($item->post_id) {
                    try {
                        $this->publisherService->syncLinkedPostFromItem($item, $runContext);
                    } catch (Throwable $syncError) {
                        Log::warning('Bot translation sync to linked post failed.', [
                            'source_key' => $sourceKey,
                            'stable_key' => (string) $item->stable_key,
                            'bot_run_id' => $runId,
                            'post_id' => (int) $item->post_id,
                            'error' => $this->limitText($syncError->getMessage(), 220),
                        ]);
                    }
                }

                if (config('app.debug')) {
                    Log::debug('Bot translation completed in runner.', [
                        'source_key' => $sourceKey,
                        'stable_key' => (string) $item->stable_key,
                        'bot_run_id' => $runId,
                        'translation_status' => $status,
                        'provider' => $translationProvider,
                        'origin_title_hash' => $this->shortHash($title),
                        'origin_body_hash' => $this->shortHash($content),
                        'translated_title_hash' => $this->shortHash($translatedTitle ?? ''),
                        'translated_body_hash' => $this->shortHash($translatedContent ?? ''),
                    ]);
                }

                if ($status === BotTranslationStatus::DONE->value) {
                    $stats['translation_done_count'] = (int) ($stats['translation_done_count'] ?? 0) + 1;
                } elseif ($status === BotTranslationStatus::FAILED->value) {
                    $stats['failed_count']++;
                    $stats['translation_failed_count'] = (int) ($stats['translation_failed_count'] ?? 0) + 1;
                }
            } catch (Throwable $e) {
                $stats['failed_count']++;
                $stats['translation_failed_count'] = (int) ($stats['translation_failed_count'] ?? 0) + 1;
                $this->recordErrorFingerprint($stats, $e);
                $errorMessage = $this->limitText($e->getMessage(), 300);
                $errorType = $this->resolveTranslationErrorType($e);
                $meta['translation_error'] = $errorMessage;
                $meta['translation_error_type'] = $errorType;
                $meta['translation'] = array_replace(
                    is_array($meta['translation'] ?? null) ? $meta['translation'] : [],
                    [
                        'provider' => $this->resolveConfiguredTranslationProvider(),
                        'reason' => $errorType,
                        'target_lang' => 'sk',
                        'translated_at' => now()->toIso8601String(),
                        'error' => $errorMessage,
                    ]
                );
                $meta['run_context'] = $runContext;
                Log::warning('Bot translation failed for item.', [
                    'source_key' => $sourceKey,
                    'stable_key' => (string) $item->stable_key,
                    'bot_run_id' => $runId,
                    'provider' => $this->resolveConfiguredTranslationProvider(),
                    'error_type' => $errorType,
                    'timeout_sec' => max(1, (int) config('bots.translation.timeout_sec', 12)),
                    'error' => $errorMessage,
                    'origin_title_hash' => $this->shortHash($title),
                    'origin_body_hash' => $this->shortHash($content),
                ]);

                $item->forceFill([
                    'translation_status' => BotTranslationStatus::FAILED->value,
                    'translation_error' => $errorMessage,
                    'translation_provider' => null,
                    'translated_at' => now(),
                    'meta' => $meta,
                ])->save();
            }
        }
    }

    /**
     * @param array<string,mixed> $meta
     */
    private function markTranslationSkipped(
        BotItem $item,
        array $meta,
        string $runContext,
        string $reason,
        string $provider
    ): void
    {
        $existingTranslationMeta = is_array($meta['translation'] ?? null) ? $meta['translation'] : [];
        $meta['translation'] = array_replace($existingTranslationMeta, [
            'provider' => $provider,
            'reason' => $reason,
            'target_lang' => 'sk',
            'translated_at' => now()->toIso8601String(),
            'error' => null,
        ]);
        unset($meta['translation_error']);
        $meta['run_context'] = $runContext;

        $item->forceFill([
            'translation_status' => BotTranslationStatus::SKIPPED->value,
            'translation_error' => null,
            'translation_provider' => $provider,
            'translated_at' => now(),
            'meta' => $meta,
        ])->save();
    }

    /**
     * @param array<string,mixed>|null $translationMeta
     */
    private function resolveTranslationProvider(?array $translationMeta, ?string $currentProvider): ?string
    {
        $candidate = $this->nullableString(data_get($translationMeta, 'provider'));
        if ($candidate !== null) {
            return strtolower($candidate);
        }

        $candidate = $this->nullableString(data_get($translationMeta, 'provider_content'));
        if ($candidate !== null) {
            return strtolower($candidate);
        }

        $candidate = $this->nullableString(data_get($translationMeta, 'provider_title'));
        if ($candidate !== null) {
            return strtolower($candidate);
        }

        return $this->nullableString($currentProvider);
    }

    private function resolveTranslatedAt(mixed $value): ?Carbon
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof Carbon) {
            return $value;
        }

        $normalized = trim((string) $value);
        if ($normalized === '') {
            return null;
        }

        try {
            return Carbon::parse($normalized);
        } catch (Throwable) {
            return null;
        }
    }

    private function resolveConfiguredTranslationProvider(): string
    {
        $configuredPrimary = strtolower(trim((string) config('bots.translation.primary', '')));
        if ($configuredPrimary !== '') {
            return $configuredPrimary;
        }

        return 'unknown';
    }

    private function isLikelySlovakText(string $title, string $content): bool
    {
        $combined = trim($title . ' ' . $content);
        if ($combined === '') {
            return false;
        }

        if ($this->stringLength($combined) < 60) {
            return false;
        }

        preg_match_all('/[^\x00-\x7F]/u', $combined, $matches);
        $diacriticsCount = count($matches[0] ?? []);

        return $diacriticsCount >= 5;
    }
}
