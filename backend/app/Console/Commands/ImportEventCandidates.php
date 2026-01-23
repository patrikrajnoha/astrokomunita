<?php

namespace App\Console\Commands;

use App\Services\EventImport\EventImportService;
use App\Services\EventImport\Parsers\HtmlTableEventParser;
use Illuminate\Console\Command;

class ImportEventCandidates extends Command
{
    protected $signature = 'events:import {sourceName} {url} {--parser=table : Parser name (table)}';

    protected $description = 'Import event candidates from an external HTML source.';

    public function __construct(
        private readonly EventImportService $importService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $parser = match ($this->option('parser')) {
            'table' => new HtmlTableEventParser(),
            default => null,
        };

        if ($parser === null) {
            $this->error('Unknown parser. Supported: table');
            return self::FAILURE;
        }

        $sourceName = $this->argument('sourceName');
        $url = $this->argument('url');

        $this->info(sprintf('Fetching %s from %s', $sourceName, $url));

        $result = $this->importService->importFromUrl($sourceName, $url, $parser);

        $this->info(sprintf('Parsed: %d', $result->total));
        $this->info(sprintf('Imported: %d', $result->imported));
        $this->info(sprintf('Duplicates skipped: %d', $result->duplicates));

        return self::SUCCESS;
    }
}
