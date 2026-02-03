<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MarkReadTest extends TestCase
{
    use RefreshDatabase;

    public function test_mark_read_requires_owner(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $notification = Notification::create([
            'user_id' => $userA->id,
            'type' => 'post_liked',
            'data' => ['post_id' => 1, 'actor_id' => $userB->id],
        ]);

        Sanctum::actingAs($userB);
        $this->postJson("/api/notifications/{$notification->id}/read")
            ->assertStatus(404);

        Sanctum::actingAs($userA);
        $this->postJson("/api/notifications/{$notification->id}/read")
            ->assertOk();

        $this->assertDatabaseMissing('notifications', [
            'id' => $notification->id,
            'read_at' => null,
        ]);
    }
}
