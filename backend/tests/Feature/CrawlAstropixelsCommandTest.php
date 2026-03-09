<?php

namespace Tests\Feature;

use App\Models\CrawlRun;
use App\Models\EventCandidate;
use Illuminate\Console\Command;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CrawlAstropixelsCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('events.astropixels.min_year', 2021);
        config()->set('events.astropixels.max_year', 2030);
        config()->set(
            'events.astropixels.base_url_pattern',
            'https://astropixels.com/almanac/almanac%2$02d/almanac%1$dcet.html'
        );
    }

    public function test_command_creates_crawl_run_and_candidates_and_skips_duplicates(): void
    {
        $html = File::get(base_path('tests/Fixtures/astropixels/almanac2026cet.html'));
        Http::fake([
            'https://astropixels.com/*' => Http::response($html, 200),
        ]);

        $this->artisan('events:crawl-astropixels --year=2026')
            ->assertSuccessful();

        $firstCount = EventCandidate::query()->count();
        $this->assertGreaterThan(0, $firstCount);

        $firstRun = CrawlRun::query()->latest('id')->first();
        $this->assertNotNull($firstRun);
        $this->assertSame('success', $firstRun->status);
        $this->assertSame(2026, $firstRun->year);
        $this->assertSame(2026, $firstRun->source_year);
        $this->assertTrue((bool) $firstRun->headers_used);
        $this->assertSame(0, (int) $firstRun->updated_candidates_count);
        $this->assertGreaterThan(0, (int) $firstRun->created_candidates_count);

        $this->artisan('events:crawl-astropixels --year=2026')
            ->assertSuccessful();

        $this->assertSame($firstCount, EventCandidate::query()->count());

        $secondRun = CrawlRun::query()->latest('id')->first();
        $this->assertNotNull($secondRun);
        $this->assertSame('success', $secondRun->status);
        $this->assertSame(0, (int) $secondRun->created_candidates_count);
        $this->assertSame(0, (int) $secondRun->updated_candidates_count);
        $this->assertGreaterThan(0, (int) $secondRun->skipped_duplicates_count);
    }

    public function test_command_retries_once_with_humans_cookie_when_challenged_with_http_409(): void
    {
        $html = File::get(base_path('tests/Fixtures/astropixels/almanac2026cet.html'));
        $challengeBody = '<script>document.cookie = "humans_21909=1"; document.location.reload(true)</script>';

        Http::fake([
            'https://astropixels.com/*' => Http::sequence()
                ->push($challengeBody, 409)
                ->push($html, 200),
        ]);

        $this->artisan('events:crawl-astropixels --year=2026')
            ->assertSuccessful();

        $this->assertGreaterThan(0, EventCandidate::query()->count());

        Http::assertSentCount(2);
        Http::assertSent(function ($request) {
            $cookies = $request->header('Cookie');
            if (! is_array($cookies)) {
                return false;
            }

            foreach ($cookies as $cookie) {
                if (str_contains((string) $cookie, 'humans_21909=1')) {
                    return true;
                }
            }

            return false;
        });
    }

    public function test_all_years_continues_after_per_year_failures_and_logs_them(): void
    {
        $html2026 = File::get(base_path('tests/Fixtures/astropixels/almanac2026cet.html'));
        $html2025 = File::get(base_path('tests/Fixtures/astropixels/almanac2025cet.html'));

        Http::fake(function ($request) use ($html2026, $html2025) {
            $url = $request->url();

            if (str_contains($url, 'almanac2026cet.html')) {
                return Http::response($html2026, 200);
            }

            if (str_contains($url, 'almanac2027cet.html')) {
                return Http::response($html2025, 200);
            }

            return Http::response('Not found', 404);
        });

        $this->artisan('events:crawl-astropixels --all-years')
            ->assertExitCode(Command::SUCCESS);

        $runs = CrawlRun::query()->where('source_name', 'astropixels')->get();
        $this->assertCount(10, $runs);
        $this->assertSame(2, $runs->where('status', 'success')->count());
        $this->assertSame(8, $runs->where('status', 'skipped')->count());
        $this->assertSame(8, $runs->where('error_code', 'astropixels_year_unavailable')->count());
        $this->assertGreaterThan(0, EventCandidate::query()->count());
    }

    public function test_command_builds_decade_specific_url_for_year_2031(): void
    {
        config()->set('events.astropixels.max_year', 2100);

        $html = File::get(base_path('tests/Fixtures/astropixels/almanac2026cet.html'));
        Http::fake([
            'https://astropixels.com/*' => Http::response($html, 200),
        ]);

        $this->artisan('events:crawl-astropixels --year=2031')
            ->assertSuccessful();

        Http::assertSent(function ($request) {
            return $request->url() === 'https://astropixels.com/almanac/almanac31/almanac2031cet.html';
        });

        $this->assertDatabaseHas('crawl_runs', [
            'source_name' => 'astropixels',
            'year' => 2031,
            'source_year' => 2031,
            'status' => 'success',
            'source_url' => 'https://astropixels.com/almanac/almanac31/almanac2031cet.html',
        ]);
    }
}
