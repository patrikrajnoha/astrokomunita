<?php

namespace Tests\Feature;

use App\Models\CrawlRun;
use App\Models\EventCandidate;
use App\Models\EventSource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CrawlRunControllerTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
            'role' => 'admin',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        Sanctum::actingAs($admin);
    }

    public function test_index_includes_translation_summary_for_each_run(): void
    {
        $source = EventSource::query()->create([
            'key' => 'imo',
            'name' => 'IMO',
            'base_url' => 'https://www.imo.net/resources/calendar/',
            'is_enabled' => true,
        ]);

        $run = CrawlRun::query()->create([
            'event_source_id' => $source->id,
            'source_name' => 'imo',
            'source_url' => 'https://www.imo.net/resources/calendar/',
            'year' => 2026,
            'started_at' => '2026-02-25 10:00:00',
            'finished_at' => '2026-02-25 10:10:00',
            'status' => 'success',
            'fetched_count' => 3,
            'created_candidates_count' => 3,
            'updated_candidates_count' => 0,
            'skipped_duplicates_count' => 0,
            'errors_count' => 0,
        ]);

        EventCandidate::query()->forceCreate([
            'event_source_id' => $source->id,
            'source_name' => 'imo',
            'source_url' => 'https://www.imo.net/resources/calendar/',
            'source_uid' => 'in-window-done-both',
            'external_id' => 'in-window-done-both',
            'source_hash' => hash('sha256', 'in-window-done-both'),
            'title' => 'Title A',
            'type' => 'meteor_shower',
            'max_at' => '2026-08-12 23:00:00',
            'translation_status' => EventCandidate::TRANSLATION_DONE,
            'translated_title' => 'Prelozeny nazov',
            'translated_description' => 'Prelozeny popis',
            'created_at' => '2026-02-25 10:06:00',
            'updated_at' => '2026-02-25 10:06:00',
        ]);

        EventCandidate::query()->forceCreate([
            'event_source_id' => $source->id,
            'source_name' => 'imo',
            'source_url' => 'https://www.imo.net/resources/calendar/',
            'source_uid' => 'in-window-failed',
            'external_id' => 'in-window-failed',
            'source_hash' => hash('sha256', 'in-window-failed'),
            'title' => 'Title B',
            'type' => 'meteor_shower',
            'max_at' => '2026-08-13 23:00:00',
            'translation_status' => EventCandidate::TRANSLATION_FAILED,
            'translation_error' => 'provider_unavailable',
            'created_at' => '2026-02-25 10:07:00',
            'updated_at' => '2026-02-25 10:07:00',
        ]);

        EventCandidate::query()->forceCreate([
            'event_source_id' => $source->id,
            'source_name' => 'imo',
            'source_url' => 'https://www.imo.net/resources/calendar/',
            'source_uid' => 'in-window-pending',
            'external_id' => 'in-window-pending',
            'source_hash' => hash('sha256', 'in-window-pending'),
            'title' => 'Title C',
            'type' => 'meteor_shower',
            'max_at' => '2026-08-14 23:00:00',
            'translation_status' => EventCandidate::TRANSLATION_PENDING,
            'created_at' => '2026-02-25 10:08:00',
            'updated_at' => '2026-02-25 10:08:00',
        ]);

        EventCandidate::query()->forceCreate([
            'event_source_id' => $source->id,
            'source_name' => 'imo',
            'source_url' => 'https://www.imo.net/resources/calendar/',
            'source_uid' => 'outside-window',
            'external_id' => 'outside-window',
            'source_hash' => hash('sha256', 'outside-window'),
            'title' => 'Title D',
            'type' => 'meteor_shower',
            'max_at' => '2026-08-15 23:00:00',
            'translation_status' => EventCandidate::TRANSLATION_DONE,
            'translated_title' => 'Mimo okna',
            'translated_description' => 'Mimo okna',
            'created_at' => '2026-02-25 12:00:00',
            'updated_at' => '2026-02-25 12:00:00',
        ]);

        $this->actingAsAdmin();

        $response = $this->getJson('/api/admin/crawl-runs?per_page=10');

        $response->assertOk();
        $response->assertJsonPath('data.0.id', $run->id);
        $response->assertJsonPath('data.0.translation.total', 3);
        $response->assertJsonPath('data.0.translation.done', 1);
        $response->assertJsonPath('data.0.translation.failed', 1);
        $response->assertJsonPath('data.0.translation.pending', 1);
        $response->assertJsonPath('data.0.translation.done_breakdown.both', 1);
        $response->assertJsonPath('data.0.translation.done_breakdown.title_only', 0);
        $response->assertJsonPath('data.0.translation.done_breakdown.description_only', 0);
        $response->assertJsonPath('data.0.translation.done_breakdown.without_text', 0);
    }
}
