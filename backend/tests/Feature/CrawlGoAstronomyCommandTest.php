<?php

namespace Tests\Feature;

use App\Enums\EventSource;
use App\Models\CrawlRun;
use App\Models\EventCandidate;
use App\Models\EventSource as EventSourceModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CrawlGoAstronomyCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_creates_crawl_run_and_imports_candidates_for_selected_year(): void
    {
        $html = File::get(base_path('tests/Fixtures/go_astronomy/calendar.html'));

        config()->set('events.go_astronomy.calendar_url', 'https://go-astronomy.test/calendar');
        Http::fake([
            'https://go-astronomy.test/*' => Http::response($html, 200),
        ]);

        $this->artisan('events:crawl-go-astronomy --year=2026')
            ->assertSuccessful();

        $this->assertDatabaseCount('event_candidates', 2);
        $this->assertDatabaseHas('event_candidates', [
            'source_name' => EventSource::GO_ASTRONOMY->value,
            'title' => 'Pi Day Eclipse Watch',
        ]);

        $run = CrawlRun::query()->latest('id')->first();
        $this->assertNotNull($run);
        $this->assertSame(EventSource::GO_ASTRONOMY->value, $run->source_name);
        $this->assertSame('success', $run->status);
        $this->assertSame(2, (int) $run->created_candidates_count);
    }

    public function test_command_is_skipped_when_source_is_disabled(): void
    {
        EventSourceModel::query()->create([
            'key' => EventSource::GO_ASTRONOMY->value,
            'name' => EventSource::GO_ASTRONOMY->label(),
            'base_url' => 'https://go-astronomy.test/calendar',
            'is_enabled' => false,
        ]);

        config()->set('events.go_astronomy.calendar_url', 'https://go-astronomy.test/calendar');
        Http::fake([
            'https://go-astronomy.test/*' => Http::response('unexpected', 500),
        ]);

        $this->artisan('events:crawl-go-astronomy --year=2026')
            ->assertSuccessful();

        $this->assertDatabaseCount('event_candidates', 0);

        $run = CrawlRun::query()->latest('id')->first();
        $this->assertNotNull($run);
        $this->assertSame('skipped', $run->status);
        $this->assertSame('source_disabled', $run->error_code);
    }
}
