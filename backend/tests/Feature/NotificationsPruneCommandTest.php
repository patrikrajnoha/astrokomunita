<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\NotificationEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class NotificationsPruneCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_prune_command_deletes_only_notifications_older_than_retention_window(): void
    {
        config()->set('notifications.retention_days', 90);

        $user = User::factory()->create();

        $oldNotification = Notification::query()->create([
            'user_id' => $user->id,
            'type' => 'post_liked',
            'data' => ['post_id' => 1],
        ]);
        $oldNotification->forceFill([
            'created_at' => now()->subDays(120),
            'updated_at' => now()->subDays(120),
        ])->saveQuietly();

        NotificationEvent::query()->create([
            'hash' => sha1('old-notification'),
            'notification_id' => $oldNotification->id,
            'created_at' => now()->subDays(120),
            'updated_at' => now()->subDays(120),
        ]);

        $recentNotification = Notification::query()->create([
            'user_id' => $user->id,
            'type' => 'event_invite',
            'data' => ['event_id' => 5],
        ]);
        $recentNotification->forceFill([
            'created_at' => now()->subDays(5),
            'updated_at' => now()->subDays(5),
        ])->saveQuietly();

        $exitCode = Artisan::call('notifications:prune');

        $this->assertSame(0, $exitCode);
        $this->assertDatabaseMissing('notifications', ['id' => $oldNotification->id]);
        $this->assertDatabaseMissing('notification_events', ['notification_id' => $oldNotification->id]);
        $this->assertDatabaseHas('notifications', ['id' => $recentNotification->id]);
    }
}
