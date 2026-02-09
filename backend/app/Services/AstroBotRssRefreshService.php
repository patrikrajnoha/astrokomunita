<?php

namespace App\Services;

use App\Models\RssItem;

class AstroBotRssRefreshService
{
    public function __construct(
        private readonly RssFetchService $fetchService,
    ) {
    }

    /**
     * @return array{
     *   created:int,
     *   skipped:int,
     *   errors:int,
     *   deleted_by_age:int,
     *   deleted_by_limit:int,
     *   deleted_total:int
     * }
     */
    public function refresh(string $source = RssFetchService::SOURCE_NASA_NEWS): array
    {
        $fetchResult = $this->fetchService->fetch($source);
        $cleanupResult = $this->cleanupNonPublished();

        return [
            'created' => (int) ($fetchResult['created'] ?? 0),
            'skipped' => (int) ($fetchResult['skipped'] ?? 0),
            'errors' => (int) ($fetchResult['errors'] ?? 0),
            'deleted_by_age' => $cleanupResult['deleted_by_age'],
            'deleted_by_limit' => $cleanupResult['deleted_by_limit'],
            'deleted_total' => $cleanupResult['deleted_total'],
        ];
    }

    /**
     * @return array{deleted_by_age:int,deleted_by_limit:int,deleted_total:int}
     */
    public function cleanupNonPublished(): array
    {
        $retentionDays = (int) config('astrobot.rss_retention_days', 30);
        $maxItems = (int) config('astrobot.rss_retention_max_items', 200);

        $deletedByAge = 0;
        $deletedByLimit = 0;

        if ($retentionDays > 0) {
            $deletedByAge = $this->deletableQuery()
                ->where('created_at', '<', now()->subDays($retentionDays))
                ->delete();
        }

        if ($maxItems > 0) {
            $idsToKeep = $this->deletableQuery()
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->take($maxItems)
                ->pluck('id');

            $deleteQuery = $this->deletableQuery();
            if ($idsToKeep->isNotEmpty()) {
                $deleteQuery->whereNotIn('id', $idsToKeep->all());
            }
            $deletedByLimit = $deleteQuery->delete();
        }

        return [
            'deleted_by_age' => $deletedByAge,
            'deleted_by_limit' => $deletedByLimit,
            'deleted_total' => $deletedByAge + $deletedByLimit,
        ];
    }

    private function deletableQuery()
    {
        return RssItem::query()
            ->where('status', '!=', RssItem::STATUS_PUBLISHED)
            ->whereNull('post_id');
    }

}
