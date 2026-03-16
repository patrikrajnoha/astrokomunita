<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventCandidate;
use App\Models\User;
use App\Services\Events\EventTitlePostEditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminEventTranslationBackfillTest extends TestCase
{
    use RefreshDatabase;

    private function configureTranslationProvider(): void
    {
        config()->set('bots.translation.primary', 'libretranslate');
        config()->set('bots.translation.fallback', 'none');
        config()->set('bots.translation.timeout_sec', 5);
        config()->set('bots.translation.max_retries', 0);
        config()->set('bots.translation.connect_timeout_sec', 3);
        config()->set('bots.translation.libretranslate.url', 'http://translation.test');
        config()->set('bots.translation.quality.enabled', false);
        config()->set('bots.translation.post_edit.enabled', false);

        Http::fake([
            'http://translation.test/*' => function ($request) {
                $sourceText = (string) data_get($request->data(), 'q', '');

                return Http::response([
                    'translatedText' => 'SK:' . $sourceText,
                ], 200);
            },
        ]);
    }

    private function createApprovedCandidateWithEvent(): array
    {
        $event = Event::query()->create([
            'title' => 'Full Moon',
            'type' => 'other',
            'start_at' => now(),
            'max_at' => now(),
            'short' => 'Full Moon short',
            'description' => 'Full Moon description',
            'visibility' => 1,
            'source_name' => 'astropixels',
            'source_uid' => 'evt-1',
            'source_hash' => hash('sha256', 'evt-1'),
        ]);

        $candidate = EventCandidate::query()->create([
            'source_name' => 'astropixels',
            'source_url' => 'https://astropixels.test/almanac2026cet.html',
            'source_uid' => 'cand-1',
            'external_id' => 'cand-1',
            'stable_key' => 'cand-1',
            'source_hash' => hash('sha256', 'cand-1'),
            'title' => 'Full Moon',
            'original_title' => 'Full Moon',
            'description' => 'Moon at apogee',
            'original_description' => 'Moon at apogee',
            'type' => 'other',
            'max_at' => now(),
            'start_at' => now(),
            'end_at' => null,
            'short' => 'Moon at apogee',
            'raw_payload' => '{}',
            'status' => EventCandidate::STATUS_APPROVED,
            'published_event_id' => $event->id,
            'translation_status' => EventCandidate::TRANSLATION_PENDING,
            'translation_error' => null,
            'translated_title' => null,
            'translated_description' => null,
        ]);

        return [$event, $candidate];
    }

    public function test_admin_endpoint_requires_authentication(): void
    {
        $this->postJson('/api/admin/events/retranslate', [
            'dry_run' => true,
        ])->assertStatus(401);
    }

    public function test_dry_run_returns_preview_without_persisting_changes(): void
    {
        $this->configureTranslationProvider();
        [$event, $candidate] = $this->createApprovedCandidateWithEvent();

        $admin = User::factory()->create([
            'is_admin' => true,
            'role' => 'admin',
            'is_active' => true,
        ]);
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/admin/events/retranslate', [
            'dry_run' => true,
            'force' => false,
            'limit' => 0,
        ]);

        $response->assertOk();
        $response->assertJsonPath('status', 'ok');
        $response->assertJsonPath('summary.dry_run', true);
        $response->assertJsonPath('summary.processed', 1);
        $response->assertJsonPath('summary.translated', 1);
        $response->assertJsonPath('summary.failed', 0);
        $response->assertJsonPath('summary.events_updated', 1);

        $candidate->refresh();
        $event->refresh();

        $this->assertSame(EventCandidate::TRANSLATION_PENDING, $candidate->translation_status);
        $this->assertNull($candidate->translated_title);
        $this->assertSame('Full Moon', $event->title);
    }

    public function test_run_retranslate_updates_candidate_and_published_event(): void
    {
        $this->configureTranslationProvider();
        [$event, $candidate] = $this->createApprovedCandidateWithEvent();

        $admin = User::factory()->create([
            'is_admin' => true,
            'role' => 'admin',
            'is_active' => true,
        ]);
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/admin/events/retranslate', [
            'dry_run' => false,
            'force' => false,
            'limit' => 0,
        ]);

        $response->assertOk();
        $response->assertJsonPath('status', 'ok');
        $response->assertJsonPath('summary.dry_run', false);
        $response->assertJsonPath('summary.processed', 1);
        $response->assertJsonPath('summary.translated', 1);
        $response->assertJsonPath('summary.failed', 0);
        $response->assertJsonPath('summary.events_updated', 1);

        $candidate->refresh();
        $event->refresh();

        $this->assertSame(EventCandidate::TRANSLATION_DONE, $candidate->translation_status);
        $this->assertSame('SK:Spln', $candidate->translated_title);
        $this->assertSame('SK:Mesiac at apogee', $candidate->translated_description);
        $this->assertNotNull($candidate->translated_at);

        $this->assertSame('SK:Spln', $event->title);
        $this->assertSame('SK:Mesiac at apogee', $event->description);
        $this->assertStringContainsString('SK:', (string) $event->short);
    }

    public function test_run_retranslate_can_apply_optional_title_postedit_when_feature_enabled(): void
    {
        $this->configureTranslationProvider();
        config()->set('events.ai.title_postedit_enabled', true);
        [$event, $candidate] = $this->createApprovedCandidateWithEvent();

        $this->mock(EventTitlePostEditService::class, function ($mock): void {
            $mock->shouldReceive('postEditTitle')
                ->once()
                ->andReturn([
                    'status' => 'success',
                    'title_sk' => 'Vyladeny nazov',
                    'fallback_used' => false,
                    'latency_ms' => 20,
                    'retry_count' => 0,
                ]);
        });

        $admin = User::factory()->create([
            'is_admin' => true,
            'role' => 'admin',
            'is_active' => true,
        ]);
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/admin/events/retranslate', [
            'dry_run' => false,
            'force' => false,
            'limit' => 0,
        ]);

        $response->assertOk();
        $response->assertJsonPath('status', 'ok');

        $candidate->refresh();
        $event->refresh();

        $this->assertSame('Vyladeny nazov', $candidate->translated_title);
        $this->assertSame('Vyladeny nazov', $event->title);
    }
}
