<?php

namespace Tests\Unit;

use App\Models\Notification;
use App\Models\Post;
use App\Models\User;
use App\Services\NotificationService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DedupPostLikedTest extends TestCase
{
    use RefreshDatabase;

    public function test_deduplicates_unread_post_like(): void
    {
        CarbonImmutable::setTestNow('2026-01-01 10:00:00');

        $recipient = User::factory()->create();
        $actor = User::factory()->create();
        $post = Post::factory()->create(['user_id' => $recipient->id]);

        $service = app(NotificationService::class);
        $service->createPostLiked($recipient->id, $actor->id, $post->id);

        CarbonImmutable::setTestNow('2026-01-01 10:05:00');
        $service->createPostLiked($recipient->id, $actor->id, $post->id);

        $this->assertEquals(1, Notification::query()->count());
        $notification = Notification::query()->first();
        $this->assertEquals($post->id, $notification->data['post_id']);
        $this->assertEquals('2026-01-01 10:05:00', $notification->created_at->format('Y-m-d H:i:s'));
        CarbonImmutable::setTestNow();
    }
}
