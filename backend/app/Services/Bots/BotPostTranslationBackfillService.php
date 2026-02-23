<?php

namespace App\Services\Bots;

use App\Enums\BotTranslationStatus;
use App\Models\BotItem;
use App\Models\BotSource;
use App\Services\Bots\Contracts\BotTranslationServiceInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Throwable;

class BotPostTranslationBackfillService
{
    public function __construct(
        private readonly BotTranslationServiceInterface $translationService,
        private readonly BotPublisherService $publisherService,
    ) {
    }

    /**
     * @return array{
     *   source_key:string,
     *   run_id:?int,
     *   limit:int,
     *   scanned:int,
     *   updated_posts:int,
     *   skipped:int,
     *   failed:int,
     *   failures:array<int,array{post_id:?int,reason:string}>
     * }
     */
    public function backfill(BotSource $source, int $limit = 10, ?int $runId = null): array
    {
        $normalizedLimit = max(1, min(100, $limit));
        $sourceKey = strtolower(trim((string) $source->key));

        $query = BotItem::query()
            ->where('source_id', $source->id)
            ->where(function (Builder $builder): void {
                $builder
                    ->whereNotNull('post_id')
                    ->orWhereNotNull('meta->post_id');
            })
            ->orderByDesc('fetched_at')
            ->orderByDesc('id');

        if ($runId !== null) {
            $query->where(function (Builder $builder) use ($runId): void {
                $builder
                    ->where('run_id', $runId)
                    ->orWhere('meta->last_seen_run_id', $runId);
            });
        }

        $items = $query->limit($normalizedLimit)->get();

        $updatedPosts = 0;
        $skipped = 0;
        $failed = 0;
        $failures = [];

        foreach ($items as $item) {
            $postId = (int) ($item->post_id ?: data_get($item->meta, 'post_id'));
            if ($postId <= 0) {
                $failed++;
                $failures[] = [
                    'post_id' => null,
                    'reason' => 'linked_post_not_found',
                ];
                continue;
            }

            if ((int) $item->post_id !== $postId) {
                $item->forceFill(['post_id' => $postId])->save();
                $item = $item->fresh() ?? $item;
            }

            $post = $item->post()->first();
            if (!$post) {
                $failed++;
                $failures[] = [
                    'post_id' => $postId,
                    'reason' => 'linked_post_not_found',
                ];
                continue;
            }

            try {
                if ($this->itemNeedsTranslation($item, $post->translation_status)) {
                    $this->translateItem($item);
                    $item = $item->fresh() ?? $item;
                }

                if (!$this->hasTranslatedPayload($item)) {
                    $failed++;
                    $failures[] = [
                        'post_id' => $post->id,
                        'reason' => 'translated_text_missing',
                    ];
                    continue;
                }

                $updated = $this->publisherService->syncLinkedPostFromItem($item, 'admin_backfill');
                if ($updated) {
                    $updatedPosts++;
                } else {
                    $skipped++;
                }
            } catch (Throwable $e) {
                $failed++;
                $reason = $this->truncateErrorText($e->getMessage(), 180) ?? 'backfill_failed';
                $failures[] = [
                    'post_id' => $post->id,
                    'reason' => $reason,
                ];
                Log::warning('Bot translation backfill failed.', [
                    'source_key' => $sourceKey,
                    'stable_key' => (string) $item->stable_key,
                    'post_id' => $post->id,
                    'error' => $reason,
                ]);
            }
        }

        return [
            'source_key' => $sourceKey,
            'run_id' => $runId,
            'limit' => $normalizedLimit,
            'scanned' => $items->count(),
            'updated_posts' => $updatedPosts,
            'skipped' => $skipped,
            'failed' => $failed,
            'failures' => $failures,
        ];
    }

    private function itemNeedsTranslation(BotItem $item, mixed $postTranslationStatus): bool
    {
        $itemStatus = strtolower(trim((string) ($item->translation_status?->value ?? $item->translation_status)));
        $postStatus = strtolower(trim((string) $postTranslationStatus));

        if ($itemStatus !== BotTranslationStatus::DONE->value) {
            return true;
        }

        if (!$this->hasTranslatedPayload($item)) {
            return true;
        }

        if ($postStatus !== BotTranslationStatus::DONE->value) {
            return true;
        }

        return false;
    }

    private function hasTranslatedPayload(BotItem $item): bool
    {
        return trim((string) $item->title_translated) !== ''
            || trim((string) $item->content_translated) !== '';
    }

    private function translateItem(BotItem $item): void
    {
        $title = trim((string) $item->title);
        $content = trim((string) ($item->content ?: $item->summary ?: ''));
        $meta = is_array($item->meta) ? $item->meta : [];

        if ($title === '' && $content === '') {
            $meta['translation'] = array_replace(
                is_array($meta['translation'] ?? null) ? $meta['translation'] : [],
                [
                    'provider' => 'none',
                    'reason' => 'empty_input',
                    'target_lang' => 'sk',
                    'error' => null,
                    'translated_at' => now()->toIso8601String(),
                ]
            );
            unset($meta['translation_error']);

            $item->forceFill([
                'translation_status' => BotTranslationStatus::SKIPPED->value,
                'translation_error' => null,
                'translation_provider' => 'none',
                'translated_at' => now(),
                'meta' => $meta,
            ])->save();

            return;
        }

        $result = $this->translationService->translate($title, $content, 'sk');
        $resultMeta = is_array($result['meta'] ?? null) ? $result['meta'] : [];
        $status = strtolower(trim((string) ($result['status'] ?? BotTranslationStatus::DONE->value)));
        if (!in_array($status, [
            BotTranslationStatus::DONE->value,
            BotTranslationStatus::SKIPPED->value,
            BotTranslationStatus::FAILED->value,
        ], true)) {
            $status = BotTranslationStatus::DONE->value;
        }

        $meta['translation'] = $resultMeta;
        unset($meta['translation_error']);

        $item->forceFill([
            'title_translated' => $this->nullableString($result['translated_title'] ?? $result['title_translated'] ?? null),
            'content_translated' => $this->nullableString($result['translated_content'] ?? $result['content_translated'] ?? null),
            'translation_status' => $status,
            'translation_error' => $this->nullableString(data_get($resultMeta, 'error')),
            'translation_provider' => $this->nullableString(data_get($resultMeta, 'provider')),
            'translated_at' => now(),
            'meta' => $meta,
        ])->save();
    }

    private function nullableString(mixed $value): ?string
    {
        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : null;
    }

    private function truncateErrorText(?string $value, int $maxLength = 180): ?string
    {
        $normalized = trim((string) $value);
        if ($normalized === '') {
            return null;
        }

        if ($maxLength <= 0) {
            return null;
        }

        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            if (mb_strlen($normalized) <= $maxLength) {
                return $normalized;
            }

            return mb_substr($normalized, 0, $maxLength);
        }

        if (strlen($normalized) <= $maxLength) {
            return $normalized;
        }

        return substr($normalized, 0, $maxLength);
    }
}
