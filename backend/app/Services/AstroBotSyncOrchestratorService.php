<?php

namespace App\Services;

use App\Jobs\EvaluateAndPublishAstroBotItemJob;
use App\Models\RssItem;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class AstroBotSyncOrchestratorService
{
    /**
     * @return array{
     *   added:int,
     *   updated:int,
     *   deleted:int,
     *   skipped:int,
     *   errors:int,
     *   published:int,
     *   needs_review:int,
     *   rejected:int,
     *   synced_at:string
     * }
     */
    public function syncAndProcess(): array
    {
        $sync = $this->rssService->sync();
        $itemIds = array_values(array_unique(array_map(
            static fn ($id): int => (int) $id,
            (array) ($sync['evaluate_item_ids'] ?? [])
        )));

        $published = 0;
        $needsReview = 0;
        $rejected = 0;

        foreach ($itemIds as $itemId) {
            $before = RssItem::query()->find($itemId);
            $beforeStatus = $before?->status;

            EvaluateAndPublishAstroBotItemJob::dispatchSync($itemId);

            $after = RssItem::query()->find($itemId);
            if (! $after) {
                continue;
            }

            if ($after->status === RssItem::STATUS_PUBLISHED && $beforeStatus !== RssItem::STATUS_PUBLISHED) {
                $published++;
                continue;
            }

            if ($after->status === RssItem::STATUS_NEEDS_REVIEW && $beforeStatus !== RssItem::STATUS_NEEDS_REVIEW) {
                $needsReview++;
                continue;
            }

            if ($after->status === RssItem::STATUS_REJECTED && $beforeStatus !== RssItem::STATUS_REJECTED) {
                $rejected++;
            }
        }

        $summary = [
            'added' => (int) ($sync['added'] ?? 0),
            'updated' => (int) ($sync['updated'] ?? 0),
            'deleted' => (int) ($sync['deleted'] ?? 0),
            'skipped' => (int) ($sync['skipped'] ?? 0),
            'errors' => (int) ($sync['errors'] ?? 0),
            'published' => $published,
            'needs_review' => $needsReview,
            'rejected' => $rejected,
            'synced_at' => (string) ($sync['synced_at'] ?? now()->toIso8601String()),
        ];

        Cache::put('astrobot:last_sync_at', $summary['synced_at'], now()->addDays(7));

        Log::info('AstroBot sync summary', $summary);

        return $summary;
    }

    public function lastSyncAt(): ?string
    {
        $value = Cache::get('astrobot:last_sync_at');
        return is_string($value) ? $value : null;
    }

    public function __construct(
        private readonly AstroBotRssService $rssService,
    ) {
    }
}

