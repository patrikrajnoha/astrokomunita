<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UnreadCountTest extends TestCase
{
    use RefreshDatabase;

    public function test_unread_count_counts_only_unread(): void
    {
        $user = User::factory()->create();

        Notification::create([
            'user_id' => $user->id,
            'type' => 'post_liked',
            'data' => ['post_id' => 1, 'actor_id' => 999],
        ]);
        Notification::create([
            'user_id' => $user->id,
            'type' => 'event_reminder',
            'data' => ['event_id' => 1, 'reminder_window' => 'T-60'],
            'read_at' => now(),
        ]);

        Sanctum::actingAs($user);
        $res = $this->getJson('/api/notifications/unread-count');
        $res->assertOk()->assertJson(['count' => 1]);
    }
}
