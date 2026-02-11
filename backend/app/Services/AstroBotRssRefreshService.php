<?php

namespace App\Services;

class AstroBotRssRefreshService
{
    public function __construct(
        private readonly AstroBotRssService $rssService,
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
        $result = $this->rssService->sync();

        return [
            'created' => (int) ($result['added'] ?? 0),
            'skipped' => (int) ($result['skipped'] ?? 0),
            'errors' => (int) ($result['errors'] ?? 0),
            'deleted_by_age' => (int) ($result['deleted'] ?? 0),
            'deleted_by_limit' => 0,
            'deleted_total' => (int) ($result['deleted'] ?? 0),
        ];
    }

    /**
     * @return array{deleted_by_age:int,deleted_by_limit:int,deleted_total:int}
     */
    public function cleanupNonPublished(): array
    {
        return ['deleted_by_age' => 0, 'deleted_by_limit' => 0, 'deleted_total' => 0];
    }
}
