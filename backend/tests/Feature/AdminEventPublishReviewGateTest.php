<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminEventPublishReviewGateTest extends TestCase
{
    use RefreshDatabase;

    public function test_publish_is_blocked_when_latest_ai_origin_contains_fallback_signal(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
        ]);

        $event = Event::query()->create([
            'title' => 'Fallback gate test',
            'description' => 'AI text',
            'type' => 'other',
            'start_at' => now()->addDay(),
            'end_at' => null,
            'max_at' => now()->addDay(),
            'short' => 'AI short',
            'visibility' => 0,
            'source_name' => 'manual',
            'source_uid' => 'manual-fallback-gate-1',
        ]);

        $this->insertOrigin(
            eventId: (int) $event->id,
            source: 'ai_generation',
            sourceDetail: 'template_guard_fallback',
            meta: [
                'generation_diagnostics' => [
                    'validation_stage' => 'json_guard',
                ],
            ]
        );

        $response = $this
            ->actingAs($admin, 'sanctum')
            ->putJson('/api/admin/events/' . $event->id, $this->eventUpdatePayload($event, 1));

        $response->assertStatus(422)
            ->assertJsonPath('error_code', 'AI_DESCRIPTION_REVIEW_REQUIRED')
            ->assertJsonPath('action', 'REVIEW_EVENT_DESCRIPTION');

        $this->assertSame(0, (int) $event->fresh()->visibility);
    }

    public function test_publish_can_be_forced_after_review_confirmation(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
        ]);

        $event = Event::query()->create([
            'title' => 'Forced publish test',
            'description' => 'AI text',
            'type' => 'other',
            'start_at' => now()->addDays(2),
            'end_at' => null,
            'max_at' => now()->addDays(2),
            'short' => 'AI short',
            'visibility' => 0,
            'source_name' => 'manual',
            'source_uid' => 'manual-fallback-gate-2',
        ]);

        $this->insertOrigin(
            eventId: (int) $event->id,
            source: 'ai_generation',
            sourceDetail: 'template',
            meta: [
                'used_fallback_base' => true,
            ]
        );

        $response = $this
            ->actingAs($admin, 'sanctum')
            ->putJson('/api/admin/events/' . $event->id, array_merge(
                $this->eventUpdatePayload($event, 1),
                ['force_publish' => true]
            ));

        $response->assertOk()
            ->assertJsonPath('data.visibility', 1);

        $this->assertSame(1, (int) $event->fresh()->visibility);
    }

    public function test_publish_is_allowed_for_non_fallback_ai_origin(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
        ]);

        $event = Event::query()->create([
            'title' => 'Standard AI publish',
            'description' => 'AI text',
            'type' => 'other',
            'start_at' => now()->addDays(3),
            'end_at' => null,
            'max_at' => now()->addDays(3),
            'short' => 'AI short',
            'visibility' => 0,
            'source_name' => 'manual',
            'source_uid' => 'manual-fallback-gate-3',
        ]);

        $this->insertOrigin(
            eventId: (int) $event->id,
            source: 'ai_generation',
            sourceDetail: 'ollama_humanized',
            meta: [
                'used_fallback_base' => false,
            ]
        );

        $response = $this
            ->actingAs($admin, 'sanctum')
            ->putJson('/api/admin/events/' . $event->id, $this->eventUpdatePayload($event, 1));

        $response->assertOk()
            ->assertJsonPath('data.visibility', 1);

        $this->assertSame(1, (int) $event->fresh()->visibility);
    }

    /**
     * @param array<string,mixed> $meta
     */
    private function insertOrigin(
        int $eventId,
        string $source,
        string $sourceDetail,
        array $meta = []
    ): void {
        DB::table('event_description_origins')->insert([
            'event_id' => $eventId,
            'source' => $source,
            'source_detail' => $sourceDetail,
            'run_id' => null,
            'candidate_id' => null,
            'description_hash' => hash('sha256', 'desc-' . $eventId),
            'short_hash' => hash('sha256', 'short-' . $eventId),
            'meta' => $meta !== [] ? json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            'created_at' => now(),
        ]);
    }

    /**
     * @return array<string,mixed>
     */
    private function eventUpdatePayload(Event $event, int $visibility): array
    {
        return [
            'title' => (string) $event->title,
            'description' => (string) $event->description,
            'type' => (string) $event->type,
            'icon_emoji' => $event->icon_emoji,
            'region_scope' => (string) ($event->region_scope ?? 'global'),
            'start_at' => optional($event->start_at)->toIso8601String(),
            'end_at' => optional($event->end_at)->toIso8601String(),
            'visibility' => $visibility,
        ];
    }
}
