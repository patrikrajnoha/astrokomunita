<?php

namespace Tests\Feature;

use App\Models\CrawlRun;
use App\Models\EventCandidate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CrawlImoCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_imo_crawler_creates_candidates_and_run_success(): void
    {
        $html = File::get(base_path('tests/Fixtures/imo/calendar_sample.html'));
        Http::fake([
            'https://www.imo.net/resources/calendar/*' => Http::response($html, 200),
        ]);

        $this->artisan('events:crawl-imo --year=2026')
            ->assertSuccessful();

        $this->assertSame(2, EventCandidate::query()->count());

        $run = CrawlRun::query()->latest('id')->first();
        $this->assertNotNull($run);
        $this->assertSame('imo', $run->source_name);
        $this->assertSame('success', $run->status);
        $this->assertSame(2, (int) $run->fetched_count);
        $this->assertSame(2, (int) $run->created_candidates_count);
        $this->assertSame(0, (int) $run->updated_candidates_count);
    }

    public function test_imo_crawler_is_idempotent_on_second_run(): void
    {
        $html = File::get(base_path('tests/Fixtures/imo/calendar_sample.html'));
        Http::fake([
            'https://www.imo.net/resources/calendar/*' => Http::response($html, 200),
        ]);

        $this->artisan('events:crawl-imo --year=2026')
            ->assertSuccessful();

        $firstCount = EventCandidate::query()->count();
        $this->assertSame(2, $firstCount);

        $this->artisan('events:crawl-imo --year=2026')
            ->assertSuccessful();

        $this->assertSame($firstCount, EventCandidate::query()->count());

        $run = CrawlRun::query()->latest('id')->first();
        $this->assertNotNull($run);
        $this->assertSame('success', $run->status);
        $this->assertSame(0, (int) $run->created_candidates_count);
        $this->assertSame(0, (int) $run->updated_candidates_count);
        $this->assertGreaterThan(0, (int) $run->skipped_duplicates_count);
    }
}

