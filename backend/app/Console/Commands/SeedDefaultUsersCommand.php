<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Database\Seeders\DemoFeedSeeder;
use Database\Seeders\DefaultUsersSeeder;

class SeedDefaultUsersCommand extends Command
{
    protected $signature = 'app:seed-default-users {--force : Run this command in production} {--without-demo-posts : Skip demo feed post seeding}';

    protected $description = 'Create or update default users and optionally seed demo feed posts';

    public function handle(DefaultUsersSeeder $defaultUsersSeeder, DemoFeedSeeder $demoFeedSeeder): int
    {
        if (app()->environment('production') && ! $this->option('force')) {
            $this->error('Refusing to run in production without --force.');
            return Command::FAILURE;
        }

        $summary = $defaultUsersSeeder->seed();

        $created = (array) ($summary['created'] ?? []);
        $updated = (array) ($summary['updated'] ?? []);

        $this->line('Created: ' . ($created === [] ? 'none' : implode(', ', $created)));
        $this->line('Updated: ' . ($updated === [] ? 'none' : implode(', ', $updated)));

        if (! $this->option('without-demo-posts')) {
            $feedSummary = $demoFeedSeeder->seed();
            $feedCreated = (array) ($feedSummary['created'] ?? []);
            $feedUpdated = (array) ($feedSummary['updated'] ?? []);
            $feedSkipped = (array) ($feedSummary['skipped'] ?? []);

            $this->line('Demo posts created: ' . ($feedCreated === [] ? 'none' : implode(', ', $feedCreated)));
            $this->line('Demo posts updated: ' . ($feedUpdated === [] ? 'none' : implode(', ', $feedUpdated)));
            $this->line('Demo posts skipped: ' . ($feedSkipped === [] ? 'none' : implode(', ', $feedSkipped)));
        }

        return Command::SUCCESS;
    }
}
