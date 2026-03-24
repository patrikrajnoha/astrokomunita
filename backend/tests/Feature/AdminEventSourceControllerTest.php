<?php

namespace Tests\Feature;

use App\Enums\EventSource as EventSourceEnum;
use App\Models\Event;
use App\Models\EventCandidate;
use App\Models\EventSource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminEventSourceControllerTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
            'role' => 'admin',
            'is_active' => true,
        ]);

        Sanctum::actingAs($admin);
    }

    public function test_admin_can_list_event_sources(): void
    {
        EventSource::query()->create([
            'key' => EventSourceEnum::ASTROPIXELS->value,
            'name' => EventSourceEnum::ASTROPIXELS->label(),
            'base_url' => 'https://astropixels.test',
            'is_enabled' => true,
        ]);
        EventSource::query()->create([
            'key' => EventSourceEnum::NASA->value,
            'name' => EventSourceEnum::NASA->label(),
            'base_url' => 'https://nasa.test',
            'is_enabled' => true,
        ]);
        EventSource::query()->create([
            'key' => 'go_astronomy',
            'name' => 'Go Astronomy Event Calendar',
            'base_url' => 'https://go-astronomy.test',
            'is_enabled' => false,
        ]);

        $this->actingAsAdmin();

        $response = $this->getJson('/api/admin/event-sources');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $response->assertJsonMissingPath('data.2');
        $keys = collect($response->json('data'))->pluck('key')->all();
        $this->assertContains(EventSourceEnum::ASTROPIXELS->value, $keys);
        $this->assertContains(EventSourceEnum::NASA->value, $keys);
        $this->assertNotContains('go_astronomy', $keys);
    }

    public function test_admin_list_auto_seeds_sources_when_table_is_empty(): void
    {
        $this->assertDatabaseCount('event_sources', 0);

        $this->actingAsAdmin();

        $response = $this->getJson('/api/admin/event-sources');

        $response->assertOk();
        $response->assertJsonCount(4, 'data');

        $keys = collect($response->json('data'))->pluck('key')->all();
        $this->assertContains(EventSourceEnum::ASTROPIXELS->value, $keys);
        $this->assertContains(EventSourceEnum::NASA->value, $keys);
        $this->assertContains(EventSourceEnum::IMO->value, $keys);
        $this->assertContains(EventSourceEnum::NASA_WATCH_THE_SKIES->value, $keys);
    }

    public function test_admin_can_toggle_source_enabled_state(): void
    {
        $source = EventSource::query()->create([
            'key' => EventSourceEnum::NASA->value,
            'name' => EventSourceEnum::NASA->label(),
            'base_url' => 'https://nasa.test',
            'is_enabled' => true,
        ]);

        $this->actingAsAdmin();

        $this->patchJson("/api/admin/event-sources/{$source->id}", [
            'is_enabled' => false,
        ])->assertOk()
            ->assertJsonPath('key', EventSourceEnum::NASA->value)
            ->assertJsonPath('is_enabled', false);

        $this->assertDatabaseHas('event_sources', [
            'id' => $source->id,
            'is_enabled' => false,
        ]);
    }

    public function test_manual_run_executes_enabled_source_and_skips_disabled_source(): void
    {
        EventSource::query()->create([
            'key' => EventSourceEnum::ASTROPIXELS->value,
            'name' => EventSourceEnum::ASTROPIXELS->label(),
            'base_url' => 'https://astropixels.com/almanac/almanac21/almanac%dcet.html',
            'is_enabled' => true,
        ]);
        EventSource::query()->create([
            'key' => EventSourceEnum::NASA->value,
            'name' => EventSourceEnum::NASA->label(),
            'base_url' => 'https://www.nasa.gov/',
            'is_enabled' => false,
        ]);
        EventSource::query()->create([
            'key' => 'go_astronomy',
            'name' => 'Go Astronomy Event Calendar',
            'base_url' => 'https://go-astronomy.test/calendar',
            'is_enabled' => false,
        ]);

        $this->actingAsAdmin();

        $html = File::get(base_path('tests/Fixtures/astropixels/almanac2026cet.html'));
        Http::fake([
            'https://astropixels.com/*' => Http::response($html, 200),
        ]);

        $response = $this->postJson('/api/admin/event-sources/run', [
            'source_keys' => [
                EventSourceEnum::ASTROPIXELS->value,
                EventSourceEnum::NASA->value,
            ],
            'year' => 2026,
        ]);

        $response->assertOk();
        $response->assertJsonPath('status', 'ok');
        $response->assertJsonPath('results.0.source_key', EventSourceEnum::ASTROPIXELS->value);
        $response->assertJsonPath('results.0.status', 'success');
        $response->assertJsonPath('results.1.source_key', EventSourceEnum::NASA->value);
        $response->assertJsonPath('results.1.status', 'skipped');

        $this->assertDatabaseHas('crawl_runs', [
            'source_name' => EventSourceEnum::ASTROPIXELS->value,
            'status' => 'success',
        ]);
    }

    public function test_manual_run_skips_astropixels_year_when_catalog_does_not_list_it(): void
    {
        config()->set('events.astropixels.catalog_fetch_during_tests', true);
        config()->set('events.astropixels.catalog_url', 'https://astropixels.test/almanac/almanac.html');

        EventSource::query()->create([
            'key' => EventSourceEnum::ASTROPIXELS->value,
            'name' => EventSourceEnum::ASTROPIXELS->label(),
            'base_url' => 'https://astropixels.com/almanac/almanac21/almanac2026cet.html',
            'is_enabled' => true,
        ]);

        $this->actingAsAdmin();

        Http::fake([
            'https://astropixels.test/almanac/almanac.html' => Http::response(
                '<a href="almanac26/almanac2026cet.html">2026</a>',
                200
            ),
            'https://astropixels.com/*' => Http::response('should_not_be_called', 500),
        ]);

        $response = $this->postJson('/api/admin/event-sources/run', [
            'source_keys' => [EventSourceEnum::ASTROPIXELS->value],
            'year' => 2031,
        ]);

        $response->assertOk();
        $response->assertJsonPath('results.0.source_key', EventSourceEnum::ASTROPIXELS->value);
        $response->assertJsonPath('results.0.status', 'skipped');
        $this->assertStringContainsString(
            'este nie je publikovany',
            (string) $response->json('results.0.message')
        );

        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request->url() === 'https://astropixels.test/almanac/almanac.html');

        $this->assertDatabaseMissing('crawl_runs', [
            'source_name' => EventSourceEnum::ASTROPIXELS->value,
            'year' => 2031,
        ]);
    }

    public function test_manual_run_executes_imo_source(): void
    {
        EventSource::query()->create([
            'key' => EventSourceEnum::IMO->value,
            'name' => EventSourceEnum::IMO->label(),
            'base_url' => 'https://www.imo.net/resources/calendar/',
            'is_enabled' => true,
        ]);

        $this->actingAsAdmin();

        $html = File::get(base_path('tests/Fixtures/imo/calendar_sample.html'));
        Http::fake([
            'https://www.imo.net/resources/calendar/*' => Http::response($html, 200),
        ]);

        $response = $this->postJson('/api/admin/event-sources/run', [
            'source_keys' => [EventSourceEnum::IMO->value],
            'year' => 2026,
        ]);

        $response->assertOk();
        $response->assertJsonPath('results.0.source_key', EventSourceEnum::IMO->value);
        $response->assertJsonPath('results.0.status', 'success');
        $response->assertJsonPath('results.0.created_candidates_count', 2);

        $this->assertDatabaseHas('crawl_runs', [
            'source_name' => EventSourceEnum::IMO->value,
            'status' => 'success',
            'created_candidates_count' => 2,
        ]);
    }

    public function test_manual_run_executes_nasa_and_nasa_watch_the_skies_sources(): void
    {
        config()->set('events.nasa.eclipses_year_url', 'https://aa.usno.navy.mil/api/eclipses/solar/year');
        config()->set('events.nasa.eclipse_date_url', 'https://aa.usno.navy.mil/api/eclipses/solar/date');
        config()->set('events.nasa_watch_the_skies.moon_phases_year_url', 'https://aa.usno.navy.mil/api/moon/phases/year');
        config()->set('events.nasa.include_only_visible', true);

        EventSource::query()->create([
            'key' => EventSourceEnum::NASA->value,
            'name' => EventSourceEnum::NASA->label(),
            'base_url' => 'https://aa.usno.navy.mil/api/eclipses/solar/year',
            'is_enabled' => true,
        ]);
        EventSource::query()->create([
            'key' => EventSourceEnum::NASA_WATCH_THE_SKIES->value,
            'name' => EventSourceEnum::NASA_WATCH_THE_SKIES->label(),
            'base_url' => 'https://aa.usno.navy.mil/api/moon/phases/year',
            'is_enabled' => true,
        ]);

        $this->actingAsAdmin();

        $yearPayload = File::get(base_path('tests/Fixtures/usno/eclipses_year_2026.json'));
        $notVisiblePayload = File::get(base_path('tests/Fixtures/usno/eclipse_not_visible.json'));
        $visiblePayload = File::get(base_path('tests/Fixtures/usno/eclipse_date_2026_08_12_bratislava.json'));
        $moonPhasesPayload = File::get(base_path('tests/Fixtures/usno/moon_phases_2026.json'));

        Http::fake(function ($request) use ($yearPayload, $notVisiblePayload, $visiblePayload, $moonPhasesPayload) {
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

            if (str_contains($url, '/api/moon/phases/year')) {
                return Http::response($moonPhasesPayload, 200, ['Content-Type' => 'application/json']);
            }

            return Http::response('Not found', 404);
        });

        $response = $this->postJson('/api/admin/event-sources/run', [
            'source_keys' => [
                EventSourceEnum::NASA->value,
                EventSourceEnum::NASA_WATCH_THE_SKIES->value,
            ],
            'year' => 2026,
        ]);

        $response->assertOk();
        $response->assertJsonPath('results.0.source_key', EventSourceEnum::NASA->value);
        $response->assertJsonPath('results.0.status', 'success');
        $response->assertJsonPath('results.0.created_candidates_count', 1);
        $response->assertJsonPath('results.1.source_key', EventSourceEnum::NASA_WATCH_THE_SKIES->value);
        $response->assertJsonPath('results.1.status', 'success');
        $response->assertJsonPath('results.1.created_candidates_count', 4);

        $this->assertDatabaseHas('crawl_runs', [
            'source_name' => EventSourceEnum::NASA->value,
            'status' => 'success',
            'created_candidates_count' => 1,
        ]);
        $this->assertDatabaseHas('crawl_runs', [
            'source_name' => EventSourceEnum::NASA_WATCH_THE_SKIES->value,
            'status' => 'success',
            'created_candidates_count' => 4,
        ]);
    }

    public function test_manual_run_rejects_go_astronomy_source_key(): void
    {
        EventSource::query()->create([
            'key' => EventSourceEnum::ASTROPIXELS->value,
            'name' => EventSourceEnum::ASTROPIXELS->label(),
            'base_url' => 'https://astropixels.com/almanac/almanac21/almanac%dcet.html',
            'is_enabled' => true,
        ]);

        $this->actingAsAdmin();

        $response = $this->postJson('/api/admin/event-sources/run', [
            'source_keys' => ['go_astronomy'],
            'year' => 2026,
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('message', 'Jeden alebo viac zdrojov nie je v tomto prostredi dostupnych.');
        $response->assertJsonPath('errors.source_keys.0', 'Source key(s) not allowed: go_astronomy');
    }

    public function test_admin_can_fetch_translation_artifacts_report(): void
    {
        $event = Event::query()->create([
            'title' => 'Jupiter v konflikte so slnkom',
            'type' => 'planetary_event',
            'start_at' => now(),
            'max_at' => now(),
            'short' => 'Jupiter v konflikte so slnkom',
            'description' => 'Jupiter v konflikte so slnkom nastane 29.10.2026.',
            'source_name' => 'astropixels',
            'source_uid' => 'event-artifacts-1',
            'source_hash' => hash('sha256', 'event-artifacts-1'),
        ]);

        $candidate = EventCandidate::query()->create([
            'source_name' => 'astropixels',
            'source_url' => 'https://astropixels.test/almanac2026cet.html',
            'source_uid' => 'candidate-artifacts-1',
            'external_id' => 'candidate-artifacts-1',
            'stable_key' => 'candidate-artifacts-1',
            'source_hash' => hash('sha256', 'candidate-artifacts-1'),
            'title' => 'Jupiter in Conjunction with Sun',
            'original_title' => 'Jupiter in Conjunction with Sun',
            'translated_title' => 'Jupiter v konflikte so slnkom',
            'description' => 'Jupiter in Conjunction with Sun occurs on 29.10.2026.',
            'original_description' => 'Jupiter in Conjunction with Sun occurs on 29.10.2026.',
            'translated_description' => 'Jupiter v konflikte so slnkom nastane 29.10.2026.',
            'type' => 'planetary_event',
            'max_at' => now(),
            'start_at' => now(),
            'short' => 'Jupiter v konflikte so slnkom',
            'raw_payload' => '{}',
            'status' => EventCandidate::STATUS_APPROVED,
            'translation_status' => EventCandidate::TRANSLATION_DONE,
            'published_event_id' => $event->id,
        ]);

        $this->actingAsAdmin();

        $response = $this->getJson('/api/admin/event-sources/translation-artifacts/report?sample=5');

        $response->assertOk();
        $response->assertJsonPath('status', 'ok');
        $response->assertJsonPath('summary.suspicious_candidates', 1);
        $response->assertJsonPath('samples.0.candidate_id', $candidate->id);
    }

    public function test_admin_can_repair_translation_artifacts_from_endpoint(): void
    {
        config()->set('translation.default_provider', 'libretranslate');
        config()->set('translation.fallback_provider', '');
        config()->set('translation.cache_enabled', false);
        config()->set('translation.libretranslate.base_url', 'http://libre.test');
        config()->set('translation.grammar.enabled', false);

        Http::fake([
            'http://libre.test/*' => function ($request) {
                return Http::response([
                    'translatedText' => (string) data_get($request->data(), 'q'),
                ], 200);
            },
        ]);

        $event = Event::query()->create([
            'title' => 'Jupiter v konflikte so slnkom',
            'type' => 'planetary_event',
            'start_at' => now(),
            'max_at' => now(),
            'short' => 'Jupiter v konflikte so slnkom',
            'description' => 'Jupiter v konflikte so slnkom nastane 29.10.2026.',
            'source_name' => 'astropixels',
            'source_uid' => 'event-artifacts-repair-1',
            'source_hash' => hash('sha256', 'event-artifacts-repair-1'),
        ]);

        $candidate = EventCandidate::query()->create([
            'source_name' => 'astropixels',
            'source_url' => 'https://astropixels.test/almanac2026cet.html',
            'source_uid' => 'candidate-artifacts-repair-1',
            'external_id' => 'candidate-artifacts-repair-1',
            'stable_key' => 'candidate-artifacts-repair-1',
            'source_hash' => hash('sha256', 'candidate-artifacts-repair-1'),
            'title' => 'Jupiter in Conjunction with Sun',
            'original_title' => 'Jupiter in Conjunction with Sun',
            'translated_title' => 'Jupiter v konflikte so slnkom',
            'description' => 'Jupiter in Conjunction with Sun occurs on 29.10.2026.',
            'original_description' => 'Jupiter in Conjunction with Sun occurs on 29.10.2026.',
            'translated_description' => 'Jupiter v konflikte so slnkom nastane 29.10.2026.',
            'type' => 'planetary_event',
            'max_at' => now(),
            'start_at' => now(),
            'short' => 'Jupiter v konflikte so slnkom',
            'raw_payload' => '{}',
            'status' => EventCandidate::STATUS_APPROVED,
            'translation_status' => EventCandidate::TRANSLATION_DONE,
            'published_event_id' => $event->id,
        ]);

        $this->actingAsAdmin();

        $response = $this->postJson('/api/admin/event-sources/translation-artifacts/repair', [
            'limit' => 10,
            'dry_run' => false,
            'sample' => 5,
        ]);

        $response->assertOk();
        $response->assertJsonPath('status', 'ok');
        $response->assertJsonPath('detected_count', 1);
        $response->assertJsonPath('summary.processed', 1);
        $response->assertJsonPath('summary.failed', 0);

        $candidate->refresh();
        $event->refresh();

        $this->assertSame('Jupiter v konjunkcii so Slnkom', (string) $candidate->translated_title);
        $this->assertSame('Jupiter v konjunkcii so Slnkom', (string) $event->title);
    }

    public function test_purge_default_preserves_published_events(): void
    {
        $source = EventSource::query()->create([
            'key' => EventSourceEnum::ASTROPIXELS->value,
            'name' => EventSourceEnum::ASTROPIXELS->label(),
            'base_url' => 'https://astropixels.test',
            'is_enabled' => true,
        ]);

        $event = Event::query()->create([
            'title' => 'Perzeidy',
            'type' => 'meteor_shower',
            'start_at' => now(),
            'max_at' => now(),
            'short' => 'Perzeidy',
            'description' => 'Test event',
            'source_name' => EventSourceEnum::ASTROPIXELS->value,
            'source_uid' => 'preserve-event-1',
            'source_hash' => hash('sha256', 'preserve-event-1'),
        ]);

        EventCandidate::query()->create([
            'event_source_id' => $source->id,
            'source_name' => EventSourceEnum::ASTROPIXELS->value,
            'source_url' => 'https://astropixels.test/e1',
            'source_uid' => 'preserve-candidate-1',
            'external_id' => 'preserve-candidate-1',
            'stable_key' => 'preserve-candidate-1',
            'source_hash' => hash('sha256', 'preserve-candidate-1'),
            'title' => 'Perzeidy candidate',
            'type' => 'meteor_shower',
            'start_at' => now(),
            'max_at' => now(),
            'short' => 'Perzeidy candidate',
            'description' => 'Candidate',
            'raw_payload' => '{}',
            'status' => EventCandidate::STATUS_PENDING,
            'translation_status' => EventCandidate::TRANSLATION_DONE,
        ]);

        \App\Models\CrawlRun::query()->create([
            'event_source_id' => $source->id,
            'source_name' => EventSourceEnum::ASTROPIXELS->value,
            'source_url' => 'https://astropixels.test',
            'status' => 'success',
            'started_at' => now()->subMinute(),
            'finished_at' => now(),
        ]);

        $this->actingAsAdmin();

        $response = $this->postJson('/api/admin/event-sources/purge', [
            'source_keys' => [EventSourceEnum::ASTROPIXELS->value],
            'dry_run' => false,
            'confirm' => 'delete_crawled_events',
        ]);

        $response->assertOk();
        $response->assertJsonPath('status', 'ok');
        $response->assertJsonPath('published_events_mode', 'preserved');
        $response->assertJsonPath('deleted.events', 0);
        $response->assertJsonPath('deleted.events_preserved', 1);
        $response->assertJsonPath('deleted.event_candidates', 1);
        $response->assertJsonPath('deleted.crawl_runs', 1);

        $this->assertDatabaseHas('events', ['id' => $event->id]);
        $this->assertDatabaseCount('event_candidates', 0);
        $this->assertDatabaseCount('crawl_runs', 0);
    }

    public function test_purge_can_delete_published_events_only_with_hard_confirm_token(): void
    {
        EventSource::query()->create([
            'key' => EventSourceEnum::ASTROPIXELS->value,
            'name' => EventSourceEnum::ASTROPIXELS->label(),
            'base_url' => 'https://astropixels.test',
            'is_enabled' => true,
        ]);

        $event = Event::query()->create([
            'title' => 'Hard purge event',
            'type' => 'meteor_shower',
            'start_at' => now(),
            'max_at' => now(),
            'short' => 'Hard purge',
            'description' => 'Test event',
            'source_name' => EventSourceEnum::ASTROPIXELS->value,
            'source_uid' => 'hard-purge-event-1',
            'source_hash' => hash('sha256', 'hard-purge-event-1'),
        ]);

        $this->actingAsAdmin();

        $this->postJson('/api/admin/event-sources/purge', [
            'source_keys' => [EventSourceEnum::ASTROPIXELS->value],
            'dry_run' => false,
            'delete_published_events' => true,
            'confirm' => 'delete_crawled_events',
        ])
            ->assertStatus(422)
            ->assertJsonPath('expected_confirm_token', 'delete_crawled_events_and_published');

        $this->assertDatabaseHas('events', ['id' => $event->id]);

        $this->postJson('/api/admin/event-sources/purge', [
            'source_keys' => [EventSourceEnum::ASTROPIXELS->value],
            'dry_run' => false,
            'delete_published_events' => true,
            'confirm' => 'delete_crawled_events_and_published',
        ])
            ->assertOk()
            ->assertJsonPath('published_events_mode', 'deleted')
            ->assertJsonPath('deleted.events', 1)
            ->assertJsonPath('deleted.events_preserved', 0);

        $this->assertDatabaseMissing('events', ['id' => $event->id]);
    }
}
