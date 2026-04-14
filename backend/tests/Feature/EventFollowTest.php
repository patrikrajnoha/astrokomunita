<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use Carbon\CarbonImmutable;
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

    public function test_user_can_save_personal_plan_for_event_and_follow_is_ensured(): void
    {
        $user = User::factory()->create();
        $event = $this->createPublishedEvent([
            'source_uid' => 'event-plan-endpoint',
            'start_at' => CarbonImmutable::parse('2026-04-17 21:30:00', 'UTC'),
            'end_at' => CarbonImmutable::parse('2026-04-17 23:10:00', 'UTC'),
            'description' => 'Najlepsie podmienky po zotmeni.',
        ]);

        Sanctum::actingAs($user);

        $response = $this->patchJson("/api/events/{$event->id}/plan", [
            'personal_note' => 'Vziat stativ a cierny caj.',
            'reminder_at' => '2026-04-17T19:00:00+00:00',
            'planned_time' => '2026-04-17T21:45:00+00:00',
            'planned_location_label' => 'Maly Karpaty vyhliadka',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('followed', true)
            ->assertJsonPath('data.id', $event->id)
            ->assertJsonPath('data.plan.personal_note', 'Vziat stativ a cierny caj.')
            ->assertJsonPath('data.plan.reminder_at', '2026-04-17T19:00:00+00:00')
            ->assertJsonPath('data.plan.planned_time', '2026-04-17T21:45:00+00:00')
            ->assertJsonPath('data.plan.planned_location_label', 'Maly Karpaty vyhliadka');

        $this->assertDatabaseHas('user_event_follows', [
            'user_id' => $user->id,
            'event_id' => $event->id,
            'personal_note' => 'Vziat stativ a cierny caj.',
            'planned_location_label' => 'Maly Karpaty vyhliadka',
        ]);
    }

    public function test_followed_events_index_includes_personal_plan_payload_when_present(): void
    {
        $user = User::factory()->create();
        $event = $this->createPublishedEvent([
            'source_uid' => 'followed-events-plan-payload',
            'start_at' => CarbonImmutable::parse('2026-05-12 20:00:00', 'UTC'),
            'end_at' => CarbonImmutable::parse('2026-05-12 23:00:00', 'UTC'),
            'description' => 'Pozorovanie po zotmeni.',
        ]);

        DB::table('user_event_follows')->insert([
            'user_id' => $user->id,
            'event_id' => $event->id,
            'personal_note' => 'Dorazit skor.',
            'reminder_at' => '2026-05-12 18:30:00',
            'planned_time' => '2026-05-12 20:30:00',
            'planned_location_label' => 'Luka pri meste',
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/me/followed-events?per_page=10');

        $response
            ->assertOk()
            ->assertJsonPath('data.0.id', $event->id)
            ->assertJsonPath('data.0.plan.personal_note', 'Dorazit skor.')
            ->assertJsonPath('data.0.plan.has_reminder', true)
            ->assertJsonPath('data.0.plan.has_planned_time', true)
            ->assertJsonPath('data.0.plan.has_planned_location', true)
            ->assertJsonPath('data.0.plan.planned_location_label', 'Luka pri meste')
            ->assertJsonPath('data.0.recommended_viewing_label', 'Odporúčaný čas okolo 22:00');
    }

    public function test_event_detail_for_authenticated_user_exposes_follow_plan_context(): void
    {
        $user = User::factory()->create();
        $event = $this->createPublishedEvent([
            'source_uid' => 'event-detail-plan-context',
            'start_at' => CarbonImmutable::parse('2026-06-20 19:00:00', 'UTC'),
            'end_at' => CarbonImmutable::parse('2026-06-20 21:00:00', 'UTC'),
        ]);

        DB::table('user_event_follows')->insert([
            'user_id' => $user->id,
            'event_id' => $event->id,
            'personal_note' => 'Skontrolovat oblacnost.',
            'reminder_at' => '2026-06-20 17:00:00',
            'planned_time' => '2026-06-20 19:30:00',
            'planned_location_label' => 'Nad mestom',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Sanctum::actingAs($user);

        $this->getJson("/api/events/{$event->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $event->id)
            ->assertJsonPath('data.is_followed', true)
            ->assertJsonPath('data.plan.personal_note', 'Skontrolovat oblacnost.')
            ->assertJsonPath('data.plan.has_data', true);
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
