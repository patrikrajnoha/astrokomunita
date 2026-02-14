<?php

namespace App\Console\Commands;

use App\Exceptions\AstroBotSyncInProgressException;
use App\Services\AstroBotNasaService;
use Illuminate\Console\Command;

class AstroBotSyncRss extends Command
{
    protected $signature = 'astrobot:sync-rss';
    protected $description = 'Synchronize NASA RSS in fully automatic AstroBot mode.';

    public function __construct(
        private readonly AstroBotNasaService $nasaService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            $stats = $this->nasaService->syncWithLock('command');
        } catch (AstroBotSyncInProgressException) {
            $this->warn('NASA RSS sync is already running.');
            return self::FAILURE;
        }

        $this->info(sprintf(
            'New: %d, Published: %d, Deleted: %d, Duration: %dms, Errors: %d',
            $stats['new'],
            $stats['published'],
            $stats['deleted'],
            $stats['duration_ms'],
            $stats['errors'],
        ));

        return $stats['errors'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
