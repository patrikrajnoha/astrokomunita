<?php

namespace App\Console\Commands;

use App\Models\Notification;
use Illuminate\Console\Command;

class PruneNotificationsCommand extends Command
{
    protected $signature = 'notifications:prune {--days=}';

    protected $description = 'Delete in-app notifications older than the configured retention window.';

    public function handle(): int
    {
        $configuredDays = (int) config('notifications.retention_days', 90);
        $days = (int) ($this->option('days') ?: $configuredDays);

        if ($days < 1) {
            $this->error('Retention days must be at least 1.');

            return self::INVALID;
        }

        $cutoff = now()->subDays($days);
        $deleted = Notification::query()
            ->where('created_at', '<', $cutoff)
            ->delete();

        $this->info(sprintf(
            'Pruned %d notifications older than %d days (cutoff: %s).',
            $deleted,
            $days,
            $cutoff->toDateTimeString()
        ));

        return self::SUCCESS;
    }
}
