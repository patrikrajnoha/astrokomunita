<?php

namespace App\Console\Commands;

use App\Enums\BotPublishStatus;
use App\Models\BotItem;
use App\Models\BotRun;
use Illuminate\Console\Command;

class BotsPurgeCommand extends Command
{
    protected $signature = 'bots:purge
        {--runs-days=90 : Delete bot_runs older than this}
        {--items-days=180 : Delete bot_items without post_id older than this}
        {--orphan-days= : Extra purge threshold for failed/skipped orphan items}
        {--dry-run : Show counts only}';

    protected $description = 'Purge old bot runs and orphan bot items based on retention policy.';

    public function handle(): int
    {
        $runsDays = max(1, (int) $this->option('runs-days'));
        $itemsDays = max(1, (int) $this->option('items-days'));
        $orphanDaysRaw = $this->option('orphan-days');
        $orphanDays = ($orphanDaysRaw === null || $orphanDaysRaw === '')
            ? null
            : max(1, (int) $orphanDaysRaw);
        $dryRun = (bool) $this->option('dry-run');

        $runsCutoff = now()->subDays($runsDays);
        $itemsCutoff = now()->subDays($itemsDays);
        $orphanCutoff = $orphanDays !== null ? now()->subDays($orphanDays) : null;

        $runsQuery = BotRun::query()->where('started_at', '<', $runsCutoff);
        $itemsQuery = BotItem::query()
            ->whereNull('post_id')
            ->where(function ($query) use ($itemsCutoff, $orphanCutoff): void {
                $query->where('fetched_at', '<', $itemsCutoff);

                if ($orphanCutoff !== null) {
                    $query->orWhere(function ($orphanQuery) use ($orphanCutoff): void {
                        $orphanQuery
                            ->where('fetched_at', '<', $orphanCutoff)
                            ->whereIn('publish_status', [
                                BotPublishStatus::FAILED->value,
                                BotPublishStatus::SKIPPED->value,
                            ]);
                    });
                }
            });

        $runsCount = (clone $runsQuery)->count();
        $itemsCount = (clone $itemsQuery)->count();

        $this->line(sprintf('runs_cutoff=%s runs_to_delete=%d', $runsCutoff->toDateString(), $runsCount));
        $this->line(sprintf('items_cutoff=%s items_to_delete=%d', $itemsCutoff->toDateString(), $itemsCount));
        if ($orphanCutoff !== null) {
            $this->line(sprintf('orphan_cutoff=%s', $orphanCutoff->toDateString()));
        }

        if ($dryRun) {
            $this->info('Dry run only. Nothing was deleted.');
            return self::SUCCESS;
        }

        $deletedRuns = (clone $runsQuery)->delete();
        $deletedItems = (clone $itemsQuery)->delete();

        $this->info(sprintf('Deleted runs=%d items=%d', $deletedRuns, $deletedItems));

        return self::SUCCESS;
    }
}
