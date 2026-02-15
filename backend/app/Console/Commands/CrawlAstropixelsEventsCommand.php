<?php

namespace App\Console\Commands;

use App\Services\Crawlers\AstropixelsCrawlerService;
use App\Services\Crawlers\CrawlContext;
use App\Services\Crawlers\CrawlerOrchestrator;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class CrawlAstropixelsEventsCommand extends Command
{
    protected $signature = 'events:crawl-astropixels
                            {--year= : One year in range}
                            {--from= : Local date YYYY-MM-DD}
                            {--to= : Local date YYYY-MM-DD}
                            {--all-years : Crawl configured year range}
                            {--dry-run : Parse and dedupe without inserts}';

    protected $description = 'Crawl Astropixels Sky Event Almanac (CET) into event_candidates.';

    public function __construct(
        private readonly AstropixelsCrawlerService $crawler,
        private readonly CrawlerOrchestrator $orchestrator,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $years = $this->resolveYears();
        $fromUtc = $this->resolveBoundDate('from', true);
        $toUtc = $this->resolveBoundDate('to', false);
        $timezone = (string) config('events.source_timezone', 'Europe/Bratislava');
        $dryRun = (bool) $this->option('dry-run');
        $hasFailures = false;

        foreach ($years as $year) {
            $context = new CrawlContext(
                year: $year,
                from: $fromUtc,
                to: $toUtc,
                timezone: $timezone,
                dryRun: $dryRun,
            );

            $run = $this->orchestrator->run($this->crawler, $context);

            $this->line(sprintf(
                '[%d] status=%s fetched=%d created=%d duplicates=%d',
                $year,
                (string) $run->status,
                (int) $run->fetched_count,
                (int) $run->created_candidates_count,
                (int) $run->skipped_duplicates_count
            ));

            if ($run->status === 'failed') {
                $hasFailures = true;
                $this->error((string) ($run->error_summary ?: 'Crawler failed.'));
            }
        }

        return $hasFailures ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return array<int, int>
     */
    private function resolveYears(): array
    {
        $minYear = (int) config('events.astropixels.min_year', 2021);
        $maxYear = (int) config('events.astropixels.max_year', 2030);

        if ((bool) $this->option('all-years')) {
            return range($minYear, $maxYear);
        }

        $requestedYear = $this->option('year');
        if ($requestedYear !== null) {
            $year = (int) $requestedYear;
        } else {
            $year = (int) now()->year;
        }

        return [$this->boundYear($year, $minYear, $maxYear)];
    }

    private function resolveBoundDate(string $option, bool $startOfDay): ?CarbonImmutable
    {
        $value = $this->option($option);
        if (!$value) {
            return null;
        }

        $timezone = (string) config('events.source_timezone', 'Europe/Bratislava');
        $dt = CarbonImmutable::parse((string) $value, $timezone);
        $dt = $startOfDay ? $dt->startOfDay() : $dt->endOfDay();

        return $dt->utc();
    }

    private function boundYear(int $year, int $minYear, int $maxYear): int
    {
        return max($minYear, min($maxYear, $year));
    }
}
