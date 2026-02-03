<?php

namespace Tests\Feature;

use App\Models\Notification;
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
}
