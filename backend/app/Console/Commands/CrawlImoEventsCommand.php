<?php

namespace App\Console\Commands;

use App\Services\Crawlers\CrawlContext;
use App\Services\Crawlers\CrawlerOrchestrator;
use App\Services\Crawlers\ImoCrawlerService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class CrawlImoEventsCommand extends Command
{
    protected $signature = 'events:crawl-imo
                            {--year= : Target year for shower peaks}
                            {--from= : Local date YYYY-MM-DD}
                            {--to= : Local date YYYY-MM-DD}
                            {--dry-run : Parse and dedupe without inserts}';

    protected $description = 'Crawl IMO meteor shower calendar into event_candidates.';

    public function __construct(
        private readonly ImoCrawlerService $crawler,
        private readonly CrawlerOrchestrator $orchestrator,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $year = (int) ($this->option('year') ?: now((string) config('events.timezone', 'Europe/Bratislava'))->year);
        $fromUtc = $this->resolveBoundDate('from', true);
        $toUtc = $this->resolveBoundDate('to', false);
        $timezone = (string) config('events.source_timezones.imo', 'UTC');
        $dryRun = (bool) $this->option('dry-run');

        $run = $this->orchestrator->run($this->crawler, new CrawlContext(
            year: $year,
            from: $fromUtc,
            to: $toUtc,
            timezone: $timezone,
            dryRun: $dryRun,
        ));

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
            $this->error(sprintf(
                '[%d] %s: %s',
                $year,
                $errorCode,
                (string) ($run->error_summary ?: 'Crawler failed.')
            ));

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function resolveBoundDate(string $option, bool $startOfDay): ?CarbonImmutable
    {
        $value = $this->option($option);
        if (! $value) {
            return null;
        }

        $timezone = (string) config('events.timezone', 'Europe/Bratislava');
        $dt = CarbonImmutable::parse((string) $value, $timezone);
        $dt = $startOfDay ? $dt->startOfDay() : $dt->endOfDay();

        return $dt->utc();
    }
}
