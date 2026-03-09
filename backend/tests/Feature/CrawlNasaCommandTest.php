<?php

namespace Tests\Feature;

use App\Models\CrawlRun;
use App\Models\EventCandidate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CrawlNasaCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_nasa_crawler_creates_candidates_for_visible_eclipses_only(): void
    {
        config()->set('events.nasa.eclipses_year_url', 'https://aa.usno.navy.mil/api/eclipses/solar/year');
        config()->set('events.nasa.eclipse_date_url', 'https://aa.usno.navy.mil/api/eclipses/solar/date');
        config()->set('events.nasa.include_only_visible', true);
        config()->set('events.nasa.location.lat', 48.1486);
        config()->set('events.nasa.location.lon', 17.1077);
        config()->set('events.nasa.location.height_m', 150);

        $yearPayload = File::get(base_path('tests/Fixtures/usno/eclipses_year_2026.json'));
        $notVisiblePayload = File::get(base_path('tests/Fixtures/usno/eclipse_not_visible.json'));
        $visiblePayload = File::get(base_path('tests/Fixtures/usno/eclipse_date_2026_08_12_bratislava.json'));

        Http::fake(function ($request) use ($yearPayload, $notVisiblePayload, $visiblePayload) {
            $url = $request->url();

            if (str_contains($url, '/api/eclipses/solar/year')) {
                return Http::response($yearPayload, 200, ['Content-Type' => 'application/json']);
            }

            if (str_contains($url, '/api/eclipses/solar/date') && str_contains($url, 'date=2026-02-17')) {
                return Http::response($notVisiblePayload, 400, ['Content-Type' => 'application/json']);
            }

            if (str_contains($url, '/api/eclipses/solar/date') && str_contains($url, 'date=2026-08-12')) {
                return Http::response($visiblePayload, 200, ['Content-Type' => 'application/json']);
            }

            return Http::response('Not found', 404);
        });

        $this->artisan('events:crawl-nasa --year=2026')
            ->assertSuccessful();

        $this->assertSame(1, EventCandidate::query()->where('source_name', 'nasa')->count());

        $candidate = EventCandidate::query()->where('source_name', 'nasa')->first();
        $this->assertNotNull($candidate);
        $this->assertSame('eclipse_solar', $candidate->type);
        $this->assertStringContainsString('Solar Eclipse', (string) $candidate->title);
        $this->assertStringContainsString('Bratislava', (string) $candidate->description);

        $run = CrawlRun::query()->latest('id')->first();
        $this->assertNotNull($run);
        $this->assertSame('nasa', $run->source_name);
        $this->assertSame('success', $run->status);
        $this->assertSame(1, (int) $run->fetched_count);
        $this->assertSame(1, (int) $run->created_candidates_count);
        $this->assertSame(0, (int) $run->updated_candidates_count);
    }
}
