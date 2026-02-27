<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventCandidate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminEventTranslationBackfillTest extends TestCase
{
    use RefreshDatabase;

    private function configureTranslationProvider(): void
    {
        config()->set('translation.default_provider', 'argos_microservice');
        config()->set('translation.fallback_provider', '');
        config()->set('translation.cache_enabled', false);
        config()->set('translation.argos_microservice.base_url', 'http://translation.test');
        config()->set('translation.argos_microservice.internal_token', 'token');

        Http::fake([
            'http://translation.test/*' => function ($request) {
                $sourceText = (string) data_get($request->data(), 'text', '');

                return Http::response([
                    'translated' => 'SK:' . $sourceText,
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
        $this->assertSame('SK:Full Moon', $candidate->translated_title);
        $this->assertSame('SK:Moon at apogee', $candidate->translated_description);
        $this->assertNotNull($candidate->translated_at);

        $this->assertSame('SK:Full Moon', $event->title);
        $this->assertSame('SK:Moon at apogee', $event->description);
        $this->assertStringContainsString('SK:', (string) $event->short);
    }
}
