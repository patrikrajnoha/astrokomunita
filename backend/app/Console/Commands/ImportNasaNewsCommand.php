<?php

namespace App\Console\Commands;

use App\Services\NasaRssImportService;
use Illuminate\Console\Command;

class ImportNasaNewsCommand extends Command
{
    protected $signature = 'news:import-nasa {--limit=20}';

    protected $description = 'Import NASA RSS news into posts as AstroBot.';

    public function __construct(
        private readonly NasaRssImportService $service,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $limit = (int) $this->option('limit');
        if ($limit <= 0) {
            $limit = 20;
        }

        try {
            $result = $this->service->import($limit);

            $this->info(sprintf(
                'NASA RSS: Loaded=%d Inserted=%d Duplicates=%d Errors=%d',
                $result['total'],
                $result['inserted'],
                $result['duplicates'],
                $result['errors']
            ));

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('NASA RSS import failed: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
