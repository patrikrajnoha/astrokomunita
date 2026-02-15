<?php

namespace Tests\Feature;

use App\Models\CrawlRun;
use App\Models\EventCandidate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CrawlAstropixelsCommandTest extends TestCase
{
    use RefreshDatabase;

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
        $this->assertGreaterThan(0, (int) $firstRun->created_candidates_count);

        $this->artisan('events:crawl-astropixels --year=2026')
            ->assertSuccessful();

        $this->assertSame($firstCount, EventCandidate::query()->count());

        $secondRun = CrawlRun::query()->latest('id')->first();
        $this->assertNotNull($secondRun);
        $this->assertSame('success', $secondRun->status);
        $this->assertSame(0, (int) $secondRun->created_candidates_count);
        $this->assertGreaterThan(0, (int) $secondRun->skipped_duplicates_count);
    }
}
