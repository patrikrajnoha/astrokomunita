<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\NotificationEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NotificationsListTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_sees_only_their_notifications(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        Notification::create([
            'user_id' => $userA->id,
            'type' => 'post_liked',
            'data' => ['post_id' => 1, 'actor_id' => $userB->id],
        ]);
        Notification::create([
            'user_id' => $userB->id,
            'type' => 'post_liked',
            'data' => ['post_id' => 2, 'actor_id' => $userA->id],
        ]);

        Sanctum::actingAs($userA);
        $res = $this->getJson('/api/notifications');
        $res->assertOk();
        $ids = collect($res->json('data'))->pluck('id')->all();

        $this->assertCount(1, $ids);
        $this->assertDatabaseHas('notifications', ['id' => $ids[0], 'user_id' => $userA->id]);
    }

    public function test_user_can_delete_their_notification(): void
    {
        $user = User::factory()->create();
        $notification = Notification::create([
            'user_id' => $user->id,
            'type' => 'post_liked',
            'data' => ['post_id' => 1, 'actor_id' => $user->id + 1],
        ]);

        NotificationEvent::create([
            'hash' => sha1('notification-test-' . $notification->id),
            'notification_id' => $notification->id,
        ]);

        Sanctum::actingAs($user);

        $this->deleteJson("/api/notifications/{$notification->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('notifications', ['id' => $notification->id]);
        $this->assertDatabaseMissing('notification_events', ['notification_id' => $notification->id]);
    }

    public function test_user_cannot_delete_someone_elses_notification(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $notification = Notification::create([
            'user_id' => $userB->id,
            'type' => 'post_liked',
            'data' => ['post_id' => 1, 'actor_id' => $userA->id],
        ]);

        Sanctum::actingAs($userA);

        $this->deleteJson("/api/notifications/{$notification->id}")
            ->assertNotFound();

        $this->assertDatabaseHas('notifications', ['id' => $notification->id, 'user_id' => $userB->id]);
    }

    public function test_user_can_delete_all_their_notifications_without_affecting_others(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        Notification::create([
            'user_id' => $userA->id,
            'type' => 'post_liked',
            'data' => ['post_id' => 1, 'actor_id' => $userB->id],
        ]);
        Notification::create([
            'user_id' => $userA->id,
            'type' => 'contest_winner',
            'data' => ['contest_id' => 10, 'post_id' => 5],
            'read_at' => now(),
        ]);
        $otherNotification = Notification::create([
            'user_id' => $userB->id,
            'type' => 'post_liked',
            'data' => ['post_id' => 2, 'actor_id' => $userA->id],
        ]);

        Sanctum::actingAs($userA);

        $this->deleteJson('/api/notifications')
            ->assertOk()
            ->assertJson(['deleted' => 2]);

        $this->assertDatabaseCount('notifications', 1);
        $this->assertDatabaseHas('notifications', ['id' => $otherNotification->id, 'user_id' => $userB->id]);
    }
}
