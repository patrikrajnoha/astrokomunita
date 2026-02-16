<?php

namespace App\Console\Commands;

use App\Services\Crawlers\CrawlContext;
use App\Services\Crawlers\CrawlerOrchestrator;
use App\Services\Crawlers\GoAstronomyCrawlerService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class CrawlGoAstronomyEventsCommand extends Command
{
    protected $signature = 'events:crawl-go-astronomy
                            {--year= : Target year to crawl}
                            {--from= : Local date YYYY-MM-DD}
                            {--to= : Local date YYYY-MM-DD}
                            {--dry-run : Parse and dedupe without inserts}';

    protected $description = 'Crawl Go Astronomy event calendar into event_candidates.';

    public function __construct(
        private readonly GoAstronomyCrawlerService $crawler,
        private readonly CrawlerOrchestrator $orchestrator,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $year = (int) ($this->option('year') ?: now()->year);
        $fromUtc = $this->resolveBoundDate('from', true);
        $toUtc = $this->resolveBoundDate('to', false);
        $timezone = (string) config('events.source_timezone', 'Europe/Bratislava');
        $dryRun = (bool) $this->option('dry-run');

        $context = new CrawlContext(
            year: $year,
            from: $fromUtc,
            to: $toUtc,
            timezone: $timezone,
            dryRun: $dryRun,
        );

        $run = $this->orchestrator->run($this->crawler, $context);

        $this->line(sprintf(
            '[%d] status=%s fetched=%d created=%d updated=%d skipped=%d errors=%d',
            $year,
            (string) $run->status,
            (int) $run->fetched_count,
            (int) $run->created_candidates_count,
            (int) $run->updated_candidates_count,
            (int) $run->skipped_duplicates_count,
            (int) $run->errors_count
        ));

        if ($run->status === 'failed') {
            $errorCode = (string) ($run->error_code ?: 'crawler_runtime_error');
            $this->error(sprintf('[%d] %s: %s', $year, $errorCode, (string) ($run->error_summary ?: 'Crawler failed.')));
            return self::FAILURE;
        }

        if ($run->status === 'skipped') {
            $this->warn((string) ($run->error_summary ?: 'Crawl skipped.'));
        }

        return self::SUCCESS;
    }

    private function resolveBoundDate(string $option, bool $startOfDay): ?CarbonImmutable
    {
        $value = $this->option($option);
        if (! $value) {
            return null;
        }

        $timezone = (string) config('events.source_timezone', 'Europe/Bratislava');
        $dt = CarbonImmutable::parse((string) $value, $timezone);
        $dt = $startOfDay ? $dt->startOfDay() : $dt->endOfDay();

        return $dt->utc();
    }
}
