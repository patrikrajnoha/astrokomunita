<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventCandidate;
use App\Models\User;
use App\Services\AI\OllamaClient;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminEventTitlePostEditEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_preview_success_returns_suggested_title_without_persisting_event(): void
    {
        config()->set('events.ai.title_postedit_admin_enabled', true);

        [$event] = $this->createEventWithCandidate(
            eventTitle: 'Jupiter 3,7° juzne od Mesiaca',
            originalTitle: 'Jupiter 3.7 S of Moon'
        );
        $this->actingAsAdmin();

        $this->mock(OllamaClient::class, function ($mock): void {
            $mock->shouldReceive('generate')
                ->once()
                ->andReturn([
                    'text' => '{"title_sk":"Jupiter 3,7° južne od Mesiaca"}',
                    'model' => 'mistral',
                    'duration_ms' => 41,
                    'retry_count' => 1,
                    'raw' => [],
                ]);
        });

        $this->postJson('/api/admin/events/' . $event->id . '/ai/postedit-title', [])
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('mode', 'preview')
            ->assertJsonPath('suggested_title_sk', 'Jupiter 3,7° južne od Mesiaca')
            ->assertJsonPath('fallback_used', false)
            ->assertJsonPath('last_run.feature_name', 'event_title_postedit')
            ->assertJsonPath('last_run.entity_id', (int) $event->id);

        $event->refresh();
        $this->assertSame('Jupiter 3,7° juzne od Mesiaca', (string) $event->title);
    }

    public function test_invalid_json_falls_back_to_literal_title(): void
    {
        config()->set('events.ai.title_postedit_admin_enabled', true);

        [$event] = $this->createEventWithCandidate(
            eventTitle: 'Spln Mesiaca',
            originalTitle: 'Full Moon'
        );
        $this->actingAsAdmin();

        $this->mock(OllamaClient::class, function ($mock): void {
            $mock->shouldReceive('generate')
                ->once()
                ->andReturn([
                    'text' => 'not-json',
                    'model' => 'mistral',
                    'duration_ms' => 20,
                    'retry_count' => 0,
                    'raw' => [],
                ]);
        });

        $this->postJson('/api/admin/events/' . $event->id . '/ai/postedit-title', [])
            ->assertOk()
            ->assertJsonPath('status', 'fallback')
            ->assertJsonPath('mode', 'preview')
            ->assertJsonPath('suggested_title_sk', 'Spln Mesiaca')
            ->assertJsonPath('fallback_used', true);
    }

    public function test_number_added_when_none_existed_uses_fallback(): void
    {
        config()->set('events.ai.title_postedit_admin_enabled', true);

        [$event] = $this->createEventWithCandidate(
            eventTitle: 'Spln Mesiaca',
            originalTitle: 'Full Moon'
        );
        $this->actingAsAdmin();

        $this->mock(OllamaClient::class, function ($mock): void {
            $mock->shouldReceive('generate')
                ->once()
                ->andReturn([
                    'text' => '{"title_sk":"Spln Mesiaca 2026"}',
                    'model' => 'mistral',
                    'duration_ms' => 22,
                    'retry_count' => 0,
                    'raw' => [],
                ]);
        });

        $this->postJson('/api/admin/events/' . $event->id . '/ai/postedit-title', [])
            ->assertOk()
            ->assertJsonPath('status', 'fallback')
            ->assertJsonPath('suggested_title_sk', 'Spln Mesiaca')
            ->assertJsonPath('fallback_used', true);
    }

    public function test_apply_mode_persists_suggested_title_when_guardrails_pass(): void
    {
        config()->set('events.ai.title_postedit_admin_enabled', true);

        [$event] = $this->createEventWithCandidate(
            eventTitle: 'Perseids meteor shower peak',
            originalTitle: 'Peak of Perseids meteor shower'
        );
        $this->actingAsAdmin();

        $this->mock(OllamaClient::class, function ($mock): void {
            $mock->shouldReceive('generate')
                ->once()
                ->andReturn([
                    'text' => '{"title_sk":"Maximum meteorickeho roja Perzeidy"}',
                    'model' => 'mistral',
                    'duration_ms' => 24,
                    'retry_count' => 0,
                    'raw' => [],
                ]);
        });

        $this->postJson('/api/admin/events/' . $event->id . '/ai/postedit-title', [
            'mode' => 'apply',
        ])
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('mode', 'apply')
            ->assertJsonPath('suggested_title_sk', 'Maximum meteorickeho roja Perzeidy')
            ->assertJsonPath('fallback_used', false);

        $event->refresh();
        $this->assertSame('Maximum meteorickeho roja Perzeidy', (string) $event->title);
    }

    public function test_postedit_title_endpoint_requires_admin_and_is_throttled(): void
    {
        config()->set('events.ai.title_postedit_admin_enabled', true);
        config()->set('admin.ai_rate_limit_per_minute', 1);

        [$event] = $this->createEventWithCandidate(
            eventTitle: 'Spln Mesiaca',
            originalTitle: 'Full Moon'
        );

        $this->mock(OllamaClient::class, function ($mock): void {
            $mock->shouldReceive('generate')
                ->andReturn([
                    'text' => '{"title_sk":"Spln Mesiaca"}',
                    'model' => 'mistral',
                    'duration_ms' => 18,
                    'retry_count' => 0,
                    'raw' => [],
                ]);
        });

        $this->postJson('/api/admin/events/' . $event->id . '/ai/postedit-title')
            ->assertStatus(401);

        $nonAdmin = User::factory()->create([
            'is_admin' => false,
            'role' => 'user',
            'is_active' => true,
        ]);
        Sanctum::actingAs($nonAdmin);
        $this->postJson('/api/admin/events/' . $event->id . '/ai/postedit-title')
            ->assertStatus(403);

        $this->actingAsAdmin();

        $ip = '10.0.9.20';
        $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->postJson('/api/admin/events/' . $event->id . '/ai/postedit-title')
            ->assertOk();

        $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->postJson('/api/admin/events/' . $event->id . '/ai/postedit-title')
            ->assertStatus(429);
    }

    private function actingAsAdmin(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
            'role' => 'admin',
            'is_active' => true,
        ]);
        Sanctum::actingAs($admin);
    }

    /**
     * @return array{0:Event,1:EventCandidate}
     */
    private function createEventWithCandidate(string $eventTitle, string $originalTitle): array
    {
        $event = Event::query()->create([
            'title' => $eventTitle,
            'type' => 'other',
            'start_at' => CarbonImmutable::parse('2026-03-02 18:00:00', 'UTC'),
            'max_at' => CarbonImmutable::parse('2026-03-02 18:00:00', 'UTC'),
            'visibility' => 1,
            'source_name' => 'manual',
            'source_uid' => 'evt-postedit-' . bin2hex(random_bytes(4)),
            'source_hash' => hash('sha256', $eventTitle . $originalTitle),
        ]);

        $candidate = EventCandidate::query()->create([
            'source_name' => 'manual',
            'source_url' => 'https://example.test/event',
            'source_uid' => 'cand-postedit-' . bin2hex(random_bytes(4)),
            'external_id' => 'cand-ext-' . bin2hex(random_bytes(3)),
            'stable_key' => 'cand-stable-' . bin2hex(random_bytes(3)),
            'source_hash' => hash('sha256', $originalTitle . microtime(true)),
            'title' => $originalTitle,
            'original_title' => $originalTitle,
            'translated_title' => $eventTitle,
            'description' => null,
            'original_description' => null,
            'translated_description' => null,
            'type' => 'other',
            'max_at' => CarbonImmutable::parse('2026-03-02 18:00:00', 'UTC'),
            'start_at' => CarbonImmutable::parse('2026-03-02 18:00:00', 'UTC'),
            'end_at' => null,
            'raw_payload' => '{}',
            'status' => EventCandidate::STATUS_APPROVED,
            'published_event_id' => $event->id,
            'translation_status' => EventCandidate::TRANSLATION_DONE,
            'reviewed_at' => CarbonImmutable::parse('2026-03-01 20:00:00', 'UTC'),
        ]);

        return [$event, $candidate];
    }
}
