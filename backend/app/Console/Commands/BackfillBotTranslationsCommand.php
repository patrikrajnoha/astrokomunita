<?php

namespace App\Console\Commands;

use App\Models\BotItem;
use App\Models\BotSource;
use App\Services\Bots\BotPostTranslationBackfillService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

class BackfillBotTranslationsCommand extends Command
{
    protected $signature = 'bots:backfill-translations
                            {--source= : Source key (optional, defaults to all sources)}
                            {--limit=20 : Max linked items to process per source (1-100)}
                            {--run-id= : Restrict processing to specific run id}
                            {--force : Retranslate even already done items (use with --apply)}
                            {--apply : Persist translation backfill (default is dry-run)}
                            {--show-failures=10 : Max per-source failures printed in apply mode}';

    protected $description = 'Backfill Slovak translations for bot-linked posts and sync translated text to feed posts.';

    public function __construct(
        private readonly BotPostTranslationBackfillService $backfillService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $sourceKey = strtolower(trim((string) $this->option('source')));
        $limit = max(1, min(100, (int) $this->option('limit')));
        $apply = (bool) $this->option('apply');
        $force = (bool) $this->option('force');
        $showFailures = max(0, min(50, (int) $this->option('show-failures')));

        $runIdOption = trim((string) $this->option('run-id'));
        $runId = null;
        if ($runIdOption !== '') {
            $runId = (int) $runIdOption;
            if ($runId <= 0) {
                $this->error('Option --run-id must be a positive integer.');
                return self::FAILURE;
            }
        }

        $sourcesQuery = BotSource::query()->orderBy('key');
        if ($sourceKey !== '') {
            $sourcesQuery->where('key', $sourceKey);
        }
        $sources = $sourcesQuery->get();

        if ($sources->isEmpty()) {
            $this->error($sourceKey !== ''
                ? sprintf('Source "%s" was not found.', $sourceKey)
                : 'No bot sources found.');
            return self::FAILURE;
        }

        if (!$apply) {
            return $this->runDry($sources->all(), $limit, $runId, $force);
        }

        return $this->runApply($sources->all(), $limit, $runId, $force, $showFailures);
    }

    /**
     * @param array<int,BotSource> $sources
     */
    private function runDry(array $sources, int $limit, ?int $runId, bool $force): int
    {
        $this->info('Dry-run mode: no changes will be persisted. Use --apply to execute.');

        $totalEligible = 0;
        $totalPlanned = 0;

        foreach ($sources as $source) {
            $eligibleQuery = $this->eligibleItemsQuery((int) $source->id, $runId);
            $eligibleCount = (clone $eligibleQuery)->count();
            $plannedCount = min($eligibleCount, $limit);
            $totalEligible += $eligibleCount;
            $totalPlanned += $plannedCount;

            $this->line(sprintf(
                '[%s] eligible=%d would_scan=%d',
                (string) $source->key,
                $eligibleCount,
                $plannedCount
            ));
        }

        $this->newLine();
        $this->info(sprintf(
            'Dry-run summary sources=%d eligible=%d planned_scan=%d limit_per_source=%d run_id=%s force=%s',
            count($sources),
            $totalEligible,
            $totalPlanned,
            $limit,
            $runId !== null ? (string) $runId : '-',
            $force ? 'yes' : 'no'
        ));

        return self::SUCCESS;
    }

    /**
     * @param array<int,BotSource> $sources
     */
    private function runApply(array $sources, int $limit, ?int $runId, bool $force, int $showFailures): int
    {
        $totals = [
            'scanned' => 0,
            'updated_posts' => 0,
            'skipped' => 0,
            'failed' => 0,
        ];

        foreach ($sources as $source) {
            $result = $this->backfillService->backfill($source, $limit, $runId, $force);

            $scanned = (int) ($result['scanned'] ?? 0);
            $updatedPosts = (int) ($result['updated_posts'] ?? 0);
            $skipped = (int) ($result['skipped'] ?? 0);
            $failed = (int) ($result['failed'] ?? 0);
            $failures = is_array($result['failures'] ?? null) ? $result['failures'] : [];

            $totals['scanned'] += $scanned;
            $totals['updated_posts'] += $updatedPosts;
            $totals['skipped'] += $skipped;
            $totals['failed'] += $failed;

            $this->line(sprintf(
                '[%s] scanned=%d updated_posts=%d skipped=%d failed=%d',
                (string) $source->key,
                $scanned,
                $updatedPosts,
                $skipped,
                $failed
            ));

            if ($failed > 0 && $showFailures > 0) {
                $visibleFailures = array_slice($failures, 0, $showFailures);
                foreach ($visibleFailures as $failure) {
                    $postId = isset($failure['post_id']) && $failure['post_id'] !== null
                        ? (string) $failure['post_id']
                        : '-';
                    $reason = trim((string) ($failure['reason'] ?? 'unknown'));
                    $this->warn(sprintf('  - post_id=%s reason=%s', $postId, $reason !== '' ? $reason : 'unknown'));
                }

                $hiddenCount = count($failures) - count($visibleFailures);
                if ($hiddenCount > 0) {
                    $this->line(sprintf('  ... and %d more failure(s)', $hiddenCount));
                }
            }
        }

        $this->newLine();
        $this->info(sprintf(
            'Backfill summary sources=%d scanned=%d updated_posts=%d skipped=%d failed=%d limit_per_source=%d run_id=%s force=%s',
            count($sources),
            $totals['scanned'],
            $totals['updated_posts'],
            $totals['skipped'],
            $totals['failed'],
            $limit,
            $runId !== null ? (string) $runId : '-',
            $force ? 'yes' : 'no'
        ));

        return $totals['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function eligibleItemsQuery(int $sourceId, ?int $runId): Builder
    {
        $query = BotItem::query()
            ->where('source_id', $sourceId)
            ->where(function (Builder $builder): void {
                $builder
                    ->whereNotNull('post_id')
                    ->orWhereNotNull('meta->post_id');
            });

        if ($runId !== null) {
            $query->where(function (Builder $builder) use ($runId): void {
                $builder
                    ->where('run_id', $runId)
                    ->orWhere('meta->last_seen_run_id', $runId);
            });
        }

        return $query;
    }
}
