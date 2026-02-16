<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Database\Seeders\DefaultUsersSeeder;

class SeedDefaultUsersCommand extends Command
{
    protected $signature = 'app:seed-default-users {--force : Run this command in production}';

    protected $description = 'Create or update default local/testing users (admin, astrobot, patrik)';

    public function handle(DefaultUsersSeeder $defaultUsersSeeder): int
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

        return Command::SUCCESS;
    }
}
