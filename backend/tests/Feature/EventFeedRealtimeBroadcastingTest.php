<?php

namespace Tests\Feature;

use App\Events\EventPublished;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event as EventFacade;
use Tests\TestCase;

class EventFeedRealtimeBroadcastingTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_event_store_broadcasts_event_published(): void
    {
        EventFacade::fake([EventPublished::class]);

        $admin = User::factory()->create([
            'is_admin' => true,
        ]);

        $response = $this
            ->actingAs($admin, 'sanctum')
            ->postJson('/api/admin/events', [
                'title' => 'Realtime Test Event',
                'description' => 'Realtime feed should receive this.',
                'type' => 'other',
                'start_at' => now()->addDay()->toIso8601String(),
                'end_at' => null,
                'visibility' => 1,
            ]);

        $response->assertCreated();

        EventFacade::assertDispatched(EventPublished::class, function (EventPublished $event) {
            $channels = $event->broadcastOn();
            $channel = $channels[0] ?? null;
            $payload = $event->broadcastWith();

            return ($channel?->name ?? null) === 'events.feed'
                && (int) ($payload['event_id'] ?? 0) > 0
                && ($payload['scope'] ?? null) === 'normal';
        });
    }

    public function test_event_update_does_not_broadcast_event_published(): void
    {
        EventFacade::fake([EventPublished::class]);

        $admin = User::factory()->create([
            'is_admin' => true,
        ]);

        $event = Event::query()->create([
            'title' => 'Existing event',
            'description' => 'Original',
            'type' => 'other',
            'start_at' => now()->addDays(2),
            'end_at' => null,
            'max_at' => now()->addDays(2),
            'visibility' => 1,
            'source_name' => 'manual',
            'source_uid' => 'manual-existing-1',
        ]);

        $response = $this
            ->actingAs($admin, 'sanctum')
            ->putJson('/api/admin/events/' . $event->id, [
                'title' => 'Updated title',
                'description' => 'Updated description',
                'type' => 'other',
                'start_at' => now()->addDays(3)->toIso8601String(),
                'end_at' => null,
                'visibility' => 1,
            ]);

        $response->assertOk();

        EventFacade::assertNotDispatched(EventPublished::class);
    }
}
