<?php

namespace App\Console\Commands;

use App\Services\RssFetchService;
use Illuminate\Console\Command;

class AstroBotFetch extends Command
{
    protected $signature = 'astrobot:fetch {--source=nasa_news}';
    protected $description = 'Fetch RSS items for AstroBot (NASA news by default)';

    public function __construct(private RssFetchService $fetchService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $source = $this->option('source');

        $this->info("Fetching RSS items for source: {$source}");

        $result = $this->fetchService->fetch($source);

        $this->info("Created: {$result['created']}, Skipped: {$result['skipped']}, Errors: {$result['errors']}");

        return Command::SUCCESS;
    }
}
