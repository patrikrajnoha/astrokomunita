<?php

namespace Tests\Feature;

use App\Models\CrawlRun;
use App\Models\EventCandidate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CrawlNasaWatchTheSkiesCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_nasa_watch_the_skies_crawler_creates_moon_phase_candidates(): void
    {
        config()->set('events.nasa_watch_the_skies.moon_phases_year_url', 'https://aa.usno.navy.mil/api/moon/phases/year');

        $payload = File::get(base_path('tests/Fixtures/usno/moon_phases_2026.json'));

        Http::fake([
            'https://aa.usno.navy.mil/api/moon/phases/year*' => Http::response($payload, 200, ['Content-Type' => 'application/json']),
        ]);

        $this->artisan('events:crawl-nasa-wts --year=2026')
            ->assertSuccessful();

        $this->assertSame(4, EventCandidate::query()->where('source_name', 'nasa_watch_the_skies')->count());

        $candidate = EventCandidate::query()
            ->where('source_name', 'nasa_watch_the_skies')
            ->orderBy('start_at')
            ->first();

        $this->assertNotNull($candidate);
        $this->assertSame('observation_window', $candidate->type);
        $this->assertStringContainsString('Moon', (string) $candidate->title);
        $this->assertStringContainsString('USNO', (string) $candidate->description);

        $run = CrawlRun::query()->latest('id')->first();
        $this->assertNotNull($run);
        $this->assertSame('nasa_watch_the_skies', $run->source_name);
        $this->assertSame('success', $run->status);
        $this->assertSame(4, (int) $run->fetched_count);
        $this->assertSame(4, (int) $run->created_candidates_count);
        $this->assertSame(0, (int) $run->updated_candidates_count);
    }

    public function test_nasa_watch_the_skies_crawler_is_idempotent_on_second_run(): void
    {
        config()->set('events.nasa_watch_the_skies.moon_phases_year_url', 'https://aa.usno.navy.mil/api/moon/phases/year');

        $payload = File::get(base_path('tests/Fixtures/usno/moon_phases_2026.json'));

        Http::fake([
            'https://aa.usno.navy.mil/api/moon/phases/year*' => Http::response($payload, 200, ['Content-Type' => 'application/json']),
        ]);

        $this->artisan('events:crawl-nasa-wts --year=2026')
            ->assertSuccessful();

        $firstCount = EventCandidate::query()->where('source_name', 'nasa_watch_the_skies')->count();
        $this->assertSame(4, $firstCount);

        $this->artisan('events:crawl-nasa-wts --year=2026')
            ->assertSuccessful();

        $this->assertSame($firstCount, EventCandidate::query()->where('source_name', 'nasa_watch_the_skies')->count());

        $run = CrawlRun::query()->latest('id')->first();
        $this->assertNotNull($run);
        $this->assertSame('success', $run->status);
        $this->assertSame(0, (int) $run->created_candidates_count);
        $this->assertSame(0, (int) $run->updated_candidates_count);
        $this->assertGreaterThan(0, (int) $run->skipped_duplicates_count);
    }
}
