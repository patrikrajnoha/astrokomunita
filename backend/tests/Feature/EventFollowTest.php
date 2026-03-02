<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EventFollowTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_manage_or_list_event_follows(): void
    {
        $event = $this->createPublishedEvent([
            'source_uid' => 'guest-follow-event',
        ]);

        $this->getJson("/api/events/{$event->id}/follow-state")->assertStatus(401);
        $this->postJson("/api/events/{$event->id}/follow")->assertStatus(401);
        $this->deleteJson("/api/events/{$event->id}/follow")->assertStatus(401);
        $this->getJson('/api/me/followed-events')->assertStatus(401);
    }

    public function test_user_can_follow_unfollow_and_check_state(): void
    {
        $user = User::factory()->create();
        $event = $this->createPublishedEvent([
            'source_uid' => 'follow-state-event',
        ]);

        Sanctum::actingAs($user);

        $this->getJson("/api/events/{$event->id}/follow-state")
            ->assertOk()
            ->assertJson(['followed' => false]);

        $this->postJson("/api/events/{$event->id}/follow")
            ->assertOk()
            ->assertJson(['followed' => true]);

        $this->postJson("/api/events/{$event->id}/follow")
            ->assertOk()
            ->assertJson(['followed' => true]);

        $this->assertDatabaseHas('user_event_follows', [
            'user_id' => $user->id,
            'event_id' => $event->id,
        ]);

        $count = DB::table('user_event_follows')
            ->where('user_id', $user->id)
            ->where('event_id', $event->id)
            ->count();

        $this->assertSame(1, $count);

        $this->getJson("/api/events/{$event->id}/follow-state")
            ->assertOk()
            ->assertJson(['followed' => true]);

        $this->deleteJson("/api/events/{$event->id}/follow")
            ->assertOk()
            ->assertJson(['followed' => false]);

        $this->getJson("/api/events/{$event->id}/follow-state")
            ->assertOk()
            ->assertJson(['followed' => false]);
    }

    public function test_followed_events_index_returns_only_current_users_events_in_desc_order(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $olderEvent = $this->createPublishedEvent([
            'title' => 'Older followed event',
            'source_uid' => 'older-followed-event',
        ]);
        $newerEvent = $this->createPublishedEvent([
            'title' => 'Newer followed event',
            'source_uid' => 'newer-followed-event',
        ]);
        $otherEvent = $this->createPublishedEvent([
            'title' => 'Other users event',
            'source_uid' => 'other-followed-event',
        ]);

        DB::table('user_event_follows')->insert([
            [
                'user_id' => $user->id,
                'event_id' => $olderEvent->id,
                'created_at' => now()->subMinutes(8),
                'updated_at' => now()->subMinutes(8),
            ],
            [
                'user_id' => $user->id,
                'event_id' => $newerEvent->id,
                'created_at' => now()->subMinute(),
                'updated_at' => now()->subMinute(),
            ],
            [
                'user_id' => $otherUser->id,
                'event_id' => $otherEvent->id,
                'created_at' => now()->subSeconds(30),
                'updated_at' => now()->subSeconds(30),
            ],
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/me/followed-events?per_page=10');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $response->assertJsonPath('data.0.id', $newerEvent->id);
        $response->assertJsonPath('data.1.id', $olderEvent->id);
        $response->assertJsonPath('total', 2);
        $this->assertNotNull(data_get($response->json(), 'data.0.followed_at'));
    }

    public function test_follow_unique_constraint_rejects_duplicates(): void
    {
        $user = User::factory()->create();
        $event = $this->createPublishedEvent([
            'source_uid' => 'duplicate-follow-event',
        ]);

        DB::table('user_event_follows')->insert([
            'user_id' => $user->id,
            'event_id' => $event->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(QueryException::class);

        DB::table('user_event_follows')->insert([
            'user_id' => $user->id,
            'event_id' => $event->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_follow_endpoints_fall_back_to_legacy_favorites_table(): void
    {
        Schema::drop('user_event_follows');

        $user = User::factory()->create();
        $event = $this->createPublishedEvent([
            'source_uid' => 'legacy-follow-event',
        ]);

        Sanctum::actingAs($user);

        $this->getJson("/api/events/{$event->id}/follow-state")
            ->assertOk()
            ->assertJson(['followed' => false]);

        $this->postJson("/api/events/{$event->id}/follow")
            ->assertOk()
            ->assertJson(['followed' => true]);

        $this->assertDatabaseHas('favorites', [
            'user_id' => $user->id,
            'event_id' => $event->id,
        ]);

        $this->getJson("/api/events/{$event->id}/follow-state")
            ->assertOk()
            ->assertJson(['followed' => true]);

        $this->getJson('/api/me/followed-events')
            ->assertOk()
            ->assertJsonPath('data.0.id', $event->id);

        $this->deleteJson("/api/events/{$event->id}/follow")
            ->assertOk()
            ->assertJson(['followed' => false]);

        $this->assertDatabaseMissing('favorites', [
            'user_id' => $user->id,
            'event_id' => $event->id,
        ]);
    }

    private function createPublishedEvent(array $overrides = []): Event
    {
        return Event::create(array_merge([
            'title' => 'Followable event',
            'type' => 'other',
            'visibility' => 1,
            'source_name' => 'manual',
            'source_uid' => 'followable-event',
            'start_at' => now()->addDay(),
            'description' => 'Event prepared for follow feature tests.',
        ], $overrides));
    }
}
