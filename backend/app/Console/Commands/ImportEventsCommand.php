<?php

namespace App\Console\Commands;

use App\Models\CrawlRun;
use App\Services\EventImport\EventImportService;
use App\Services\EventImport\Parsers\HtmlTableEventParser;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class ImportEventsCommand extends Command
{
    // dôležité: iný signature než events:import
    protected $signature = 'events:import:tracked {sourceName} {url} {--parser=table : Parser name (table)}';

    protected $description = 'Tracked import: logs CrawlRun + runs import into event_candidates';

    public function __construct(
        private readonly EventImportService $importService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $sourceName = (string) $this->argument('sourceName');
        $url = (string) $this->argument('url');

        $run = CrawlRun::create([
            'source_name' => $sourceName,
            'source_url' => $url,
            'started_at' => CarbonImmutable::now(),
        ]);

        try {
            // parser (rovnako ako v ImportEventCandidates)
            $parser = match ($this->option('parser')) {
                'table' => new HtmlTableEventParser(),
                default => null,
            };

            if ($parser === null) {
                $run->errors_count = 1;
                $run->error_log = 'Unknown parser. Supported: table';
                $run->finished_at = CarbonImmutable::now();
                $run->save();

                $this->error('Unknown parser. Supported: table');
                return self::FAILURE;
            }

            // 12.5A + 12.5B (bonus metriky z raw HTML — hlavne pre file:// test)
            $html = $this->fetchHtmlForMetrics($url);
            $run->fetched_bytes = $html !== null ? strlen($html) : 0;

            // Hlavný import (real pipeline)
            $result = $this->importService->importFromUrl($sourceName, $url, $parser);

            // Najpresnejšie metriky z pipeline výsledku
            $run->parsed_items = (int) $result->total;
            $run->inserted_candidates = (int) $result->imported;
            $run->duplicates = (int) $result->duplicates;

            $run->finished_at = CarbonImmutable::now();
            $run->save();

            $this->info(sprintf('Tracked import done. Parsed=%d Imported=%d Duplicates=%d', $result->total, $result->imported, $result->duplicates));

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $run->errors_count = 1;
            $run->error_log = (string) $e;
            $run->finished_at = CarbonImmutable::now();
            $run->save();

            $this->error($e->getMessage());
            return self::FAILURE;
        }
    }

    /**
     * Pomocný fetch len pre metriky (kým nechceš riešiť HTTP bytes).
     * - file://... -> načíta lokálny súbor (super pre test)
     * - iné URL -> null (bytes ostanú 0)
     */
    private function fetchHtmlForMetrics(string $url): ?string
    {
        if (str_starts_with($url, 'file://')) {
            $path = substr($url, strlen('file://'));
            $path = str_replace('/', DIRECTORY_SEPARATOR, $path);

            if (is_file($path)) {
                $content = @file_get_contents($path);
                return $content === false ? null : $content;
            }
        }

        return null;
    }
}
