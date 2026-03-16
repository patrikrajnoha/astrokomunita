<?php

namespace App\Http\Controllers\Api\Admin\Concerns;

use App\Enums\BotPublishStatus;
use App\Models\BotItem;
use App\Models\BotRun;
use App\Models\BotSource;
use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Throwable;

trait ManagesBotPublishing
{
    public function publishItem(Request $request, int $botItemId): JsonResponse
    {
        $request->validate([
            'force' => 'sometimes|boolean',
        ]);

        $item = BotItem::query()->find($botItemId);
        if (!$item) {
            return response()->json([
                'message' => 'Položka bota sa nenašla.',
            ], 404);
        }

        $publishStatus = strtolower(trim((string) ($item->publish_status?->value ?? $item->publish_status)));
        if ($item->post_id || $publishStatus === BotPublishStatus::PUBLISHED->value) {
            return response()->json([
                'message' => 'Polozka je uz publikovana.',
                'already_published' => true,
                'item' => $this->serializeItem($item->fresh() ?? $item),
            ]);
        }

        if ($publishStatus === BotPublishStatus::SKIPPED->value) {
            $skipReason = $this->nullableString(data_get($item->meta, 'skip_reason'));

            return response()->json([
                'message' => 'Polozka je preskocena a neda sa publikovat.',
                'skip_reason' => $skipReason,
            ], 422);
        }

        $result = $this->publisherService->publishItemToAstroFeed($item, 'admin');
        $item = $item->fresh() ?? $item;

        if ($result->isPublished() || $item->post_id || $this->isPublishedStatus($item)) {
            $item = $this->markItemPublishedManually($item);

            return response()->json([
                'message' => 'Polozka publikovana.',
                'already_published' => false,
                'item' => $this->serializeItem($item),
            ]);
        }

        $skipReason = $result->reason ?? $this->nullableString(data_get($item->meta, 'skip_reason'));

        return response()->json([
            'message' => 'Polozku sa nepodarilo publikovat.',
            'skip_reason' => $skipReason,
            'item' => $this->serializeItem($item),
        ], 422);
    }

    public function publishRun(Request $request, int $runId): JsonResponse
    {
        $validated = $request->validate([
            'publish_limit' => 'nullable|integer|min:1|max:100',
        ]);

        $run = BotRun::query()->find($runId);
        if (!$run) {
            return response()->json([
                'message' => 'Beh sa nenašiel.',
            ], 404);
        }

        $limit = isset($validated['publish_limit']) ? (int) $validated['publish_limit'] : 10;
        $items = $this->runLinkedItemsQuery($run)
            ->whereNull('post_id')
            ->where('publish_status', BotPublishStatus::PENDING->value)
            ->orderBy('fetched_at')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $publishedItemIds = [];
        $skippedCount = 0;
        $failedCount = 0;

        foreach ($items as $item) {
            try {
                $result = $this->publisherService->publishItemToAstroFeed($item, 'admin');
                $item = $item->fresh() ?? $item;

                if ($result->isPublished() || $item->post_id || $this->isPublishedStatus($item)) {
                    $item = $this->markItemPublishedManually($item);
                    $publishedItemIds[] = $item->id;
                    continue;
                }

                $skippedCount++;
            } catch (\Throwable) {
                $failedCount++;
            }
        }

        return response()->json([
            'run_id' => $run->id,
            'publish_limit' => $limit,
            'attempted_count' => $items->count(),
            'published_count' => count($publishedItemIds),
            'skipped_count' => $skippedCount,
            'failed_count' => $failedCount,
            'published_item_ids' => $publishedItemIds,
        ]);
    }

    public function deleteItemPost(int $botItemId): JsonResponse
    {
        $item = BotItem::query()->find($botItemId);
        if (!$item) {
            return response()->json([
                'message' => 'Položka bota sa nenašla.',
            ], 404);
        }

        $postId = (int) ($item->post_id ?? 0);
        if ($postId <= 0) {
            return response()->json([
                'message' => 'Položka nemá publikovaný príspevok na vymazanie.',
            ], 422);
        }

        $post = Post::query()->find($postId);
        if ($post) {
            $this->postService->deletePost($post);
        }

        $item = $this->markItemPostVymazaneManually($item, $postId);

        return response()->json([
            'message' => 'Publikovaný príspevok bol vymazaný.',
            'item' => $this->serializeItem($item),
            'deleted_post_id' => $postId,
        ]);
    }

    public function deleteAllPosts(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'source_key' => 'nullable|string|max:120',
            'bot_identity' => 'nullable|string|in:kozmo,stela',
        ]);

        $sourceKey = strtolower(trim((string) ($validated['source_key'] ?? '')));
        $botIdentity = strtolower(trim((string) ($validated['bot_identity'] ?? '')));

        $query = BotItem::query()
            ->whereNotNull('post_id')
            ->where('post_id', '>', 0);

        if ($sourceKey !== '') {
            $source = BotSource::query()->where('key', $sourceKey)->first();
            if (!$source) {
                return response()->json([
                    'message' => sprintf('Bot source "%s" was not found.', $sourceKey),
                ], 404);
            }

            $query->where('source_id', (int) $source->id);
        }

        if ($botIdentity !== '') {
            $query->where('bot_identity', $botIdentity);
        }

        $matchedCount = (clone $query)->count();
        if ($matchedCount <= 0) {
            return response()->json([
                'message' => 'Pre vybrané filtre sa nenašli publikované bot príspevky.',
                'matched_items' => 0,
                'deleted_posts' => 0,
                'missing_posts' => 0,
                'updated_items' => 0,
                'failed_items' => 0,
                'sample_deleted_post_ids' => [],
            ]);
        }

        $deletedPosts = 0;
        $missingPosts = 0;
        $updatedItems = 0;
        $failedItems = 0;
        $sampleVymazanePostIds = [];

        $query
            ->orderBy('id')
            ->chunkById(100, function ($items) use (
                &$deletedPosts,
                &$missingPosts,
                &$updatedItems,
                &$failedItems,
                &$sampleVymazanePostIds
            ): void {
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
                            if (count($sampleVymazanePostIds) < 50) {
                                $sampleVymazanePostIds[] = $postId;
                            }
                        } else {
                            $missingPosts++;
                        }

                        $this->markItemPostVymazaneManually($item, $postId);
                        $updatedItems++;
                    } catch (Throwable $e) {
                        $failedItems++;
                        Log::warning('Admin failed to delete published bot post in bulk.', [
                            'bot_item_id' => $item->id,
                            'post_id' => $postId,
                            'error' => $this->truncateErrorText($e->getMessage(), 240),
                        ]);
                    }
                }
            }, 'id');

        return response()->json([
            'message' => 'Hromadne mazanie dokoncene.',
            'matched_items' => $matchedCount,
            'deleted_posts' => $deletedPosts,
            'missing_posts' => $missingPosts,
            'updated_items' => $updatedItems,
            'failed_items' => $failedItems,
            'sample_deleted_post_ids' => $sampleVymazanePostIds,
        ]);
    }

    public function postRetention(): JsonResponse
    {
        return response()->json([
            'data' => $this->botPostRetentionService->settingsPayload(),
        ]);
    }

    public function updatePostRetention(Request $request): JsonResponse
    {
        $allowedHours = $this->botPostRetentionService->settingsPayload()['allowed_hours'] ?? [];
        $validated = $request->validate([
            'enabled' => 'sometimes|boolean',
            'auto_delete_after_hours' => ['sometimes', 'integer', Rule::in($allowedHours)],
        ]);

        $updated = $this->botPostRetentionService->updateSettings(
            array_key_exists('enabled', $validated)
                ? (bool) $validated['enabled']
                : null,
            array_key_exists('auto_delete_after_hours', $validated)
                ? (int) $validated['auto_delete_after_hours']
                : null,
        );

        return response()->json([
            'data' => $updated,
        ]);
    }

    public function runPostRetentionCleanup(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'limit' => 'nullable|integer|min:1|max:1000',
        ]);

        $result = $this->botPostRetentionService->cleanupExpiredPosts(
            isset($validated['limit']) ? (int) $validated['limit'] : 200
        );

        return response()->json([
            'message' => 'Cistenie retention bot prispevkov dokoncene.',
            'data' => $result,
        ]);
    }
}
