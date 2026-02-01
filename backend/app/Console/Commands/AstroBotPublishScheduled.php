<?php

namespace App\Console\Commands;

use App\Services\AstroBotPublisher;
use Illuminate\Console\Command;

class AstroBotPublishScheduled extends Command
{
    protected $signature = 'astrobot:publish-scheduled';
    protected $description = 'Publish scheduled RSS items whose time has come';

    public function __construct(private AstroBotPublisher $publisher)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('Publishing scheduled items...');

        $count = $this->publisher->publishScheduled();

        $this->info("Published {$count} scheduled items.");

        return Command::SUCCESS;
    }
}
