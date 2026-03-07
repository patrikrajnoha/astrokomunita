<?php

namespace App\Services\Bots;

use App\Enums\BotPublishStatus;
use App\Models\AppSetting;
use App\Models\BotItem;
use App\Models\Post;
use App\Services\PostService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class BotPostRetentionService
{
    public const SETTING_ENABLED_KEY = 'bots.posts.auto_delete_enabled';
    public const SETTING_AFTER_HOURS_KEY = 'bots.posts.auto_delete_after_hours';

    private const CACHE_KEY = 'bots:posts:auto_delete:settings';

    /**
     * @var array<int,int>
     */
    private const DEFAULT_ALLOWED_HOURS = [24, 48, 72, 168];

    public function __construct(
        private readonly PostService $postService,
    ) {
    }

    /**
     * @return array{
     *   enabled:bool,
     *   auto_delete_after_hours:int,
     *   allowed_hours:array<int,int>,
     *   scheduled_frequency:string
     * }
     */
    public function settingsPayload(): array
    {
        $cached = Cache::get(self::CACHE_KEY);
        if (is_array($cached)) {
            return $cached;
        }

        $allowedHours = $this->allowedHours();
        $defaultEnabled = (bool) config('bots.post_retention.enabled', false);
        $defaultHours = (int) config('bots.post_retention.after_hours', 48);

        $payload = [
            'enabled' => AppSetting::getBool(self::SETTING_ENABLED_KEY, $defaultEnabled),
            'auto_delete_after_hours' => $this->normalizeAfterHours(
                AppSetting::getInt(self::SETTING_AFTER_HOURS_KEY, $defaultHours),
                $allowedHours
            ),
            'allowed_hours' => $allowedHours,
            'scheduled_frequency' => 'hourly',
        ];

        $this->cachePayload($payload);

        return $payload;
    }

    /**
     * @return array{
     *   enabled:bool,
     *   auto_delete_after_hours:int,
     *   allowed_hours:array<int,int>,
     *   scheduled_frequency:string
     * }
     */
    public function updateSettings(?bool $enabled = null, ?int $afterHours = null): array
    {
        $current = $this->settingsPayload();
        $nextEnabled = $enabled ?? (bool) ($current['enabled'] ?? false);
        $nextHours = $afterHours === null
            ? (int) ($current['auto_delete_after_hours'] ?? 48)
            : $this->normalizeAfterHours($afterHours, $this->allowedHours());

        AppSetting::put(self::SETTING_ENABLED_KEY, $nextEnabled ? '1' : '0');
        AppSetting::put(self::SETTING_AFTER_HOURS_KEY, (string) $nextHours);

        $payload = [
            'enabled' => $nextEnabled,
            'auto_delete_after_hours' => $nextHours,
            'allowed_hours' => $this->allowedHours(),
            'scheduled_frequency' => 'hourly',
        ];

        $this->cachePayload($payload);

        return $payload;
    }

    public function isEnabled(): bool
    {
        return (bool) ($this->settingsPayload()['enabled'] ?? false);
    }

    public function configuredAfterHours(): int
    {
        return (int) ($this->settingsPayload()['auto_delete_after_hours'] ?? 48);
    }

    /**
     * @return array{
     *   matched_items:int,
     *   processed_items:int,
     *   deleted_posts:int,
     *   missing_posts:int,
     *   updated_items:int,
     *   failed_items:int,
     *   retention_hours:int,
     *   cutoff_at:string
     * }
     */
    public function cleanupExpiredPosts(int $limit = 200, ?int $afterHours = null): array
    {
        $settings = $this->settingsPayload();
        $retentionHours = $afterHours === null
            ? (int) ($settings['auto_delete_after_hours'] ?? 48)
            : $this->normalizeAfterHours($afterHours, $this->allowedHours());

        $limit = max(1, min(1000, $limit));
        $cutoff = now()->subHours($retentionHours);

        $query = BotItem::query()
            ->whereNotNull('post_id')
            ->where('post_id', '>', 0)
            ->whereHas('post', function (Builder $postQuery) use ($cutoff): void {
                $postQuery
                    ->where('author_kind', 'bot')
                    ->where('created_at', '<=', $cutoff);
            });

        $matchedItems = (clone $query)->count();
        if ($matchedItems <= 0) {
            return [
                'matched_items' => 0,
                'processed_items' => 0,
                'deleted_posts' => 0,
                'missing_posts' => 0,
                'updated_items' => 0,
                'failed_items' => 0,
                'retention_hours' => $retentionHours,
                'cutoff_at' => $cutoff->toIso8601String(),
            ];
        }

        $items = $query
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $deletedPosts = 0;
        $missingPosts = 0;
        $updatedItems = 0;
        $failedItems = 0;

        foreach ($items as $item) {
            $postId = (int) ($item->post_id ?? 0);
            if ($postId <= 0) {
                continue;
            }

            try {
                $post = Post::query()->find($postId);
                if ($post) {
                    $this->postService->deletePost($post);
                    $deletedPosts++;
                } else {
                    $missingPosts++;
                }

                $this->markItemPostDeletedByRetention($item, $postId);
                $updatedItems++;
            } catch (Throwable $e) {
                $failedItems++;
                Log::warning('Bot post retention cleanup failed for item.', [
                    'bot_item_id' => $item->id,
                    'post_id' => $postId,
                    'error' => $this->truncateErrorText($e->getMessage()),
                ]);
            }
        }

        return [
            'matched_items' => $matchedItems,
            'processed_items' => (int) $items->count(),
            'deleted_posts' => $deletedPosts,
            'missing_posts' => $missingPosts,
            'updated_items' => $updatedItems,
            'failed_items' => $failedItems,
            'retention_hours' => $retentionHours,
            'cutoff_at' => $cutoff->toIso8601String(),
        ];
    }

    /**
     * @return array<int,int>
     */
    private function allowedHours(): array
    {
        $configured = (array) config('bots.post_retention.allowed_hours', self::DEFAULT_ALLOWED_HOURS);
        $normalized = array_values(array_unique(array_filter(array_map(
            static fn ($value): int => (int) $value,
            $configured
        ), static fn (int $value): bool => $value > 0)));

        sort($normalized);

        if ($normalized === []) {
            return self::DEFAULT_ALLOWED_HOURS;
        }

        return $normalized;
    }

    /**
     * @param array<int,int> $allowedHours
     */
    private function normalizeAfterHours(int $value, array $allowedHours): int
    {
        if (in_array($value, $allowedHours, true)) {
            return $value;
        }

        return (int) $allowedHours[0];
    }

    /**
     * @param array{
     *   enabled:bool,
     *   auto_delete_after_hours:int,
     *   allowed_hours:array<int,int>,
     *   scheduled_frequency:string
     * } $payload
     */
    private function cachePayload(array $payload): void
    {
        Cache::put(self::CACHE_KEY, $payload, now()->addMinutes(10));
    }

    private function markItemPostDeletedByRetention(BotItem $item, int $postId): BotItem
    {
        $meta = is_array($item->meta) ? $item->meta : [];
        $meta['deleted_by_retention'] = true;
        $meta['retention_deleted_at'] = now()->toIso8601String();
        $meta['deleted_post_id'] = $postId;

        $item->forceFill([
            'post_id' => null,
            'publish_status' => BotPublishStatus::PENDING->value,
            'meta' => $meta,
        ])->save();

        return $item->fresh() ?? $item;
    }

    private function truncateErrorText(string $message, int $maxLength = 240): string
    {
        $normalized = trim(preg_replace('/\s+/', ' ', $message) ?: '');
        if ($normalized === '') {
            return 'unknown_error';
        }

        if (mb_strlen($normalized) <= $maxLength) {
            return $normalized;
        }

        return mb_substr($normalized, 0, $maxLength - 3) . '...';
    }
}

