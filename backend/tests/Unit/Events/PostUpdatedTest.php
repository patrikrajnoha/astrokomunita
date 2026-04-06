<?php

namespace Tests\Unit\Events;

use App\Events\PostUpdated;
use App\Models\Post;
use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostUpdatedTest extends TestCase
{
    use RefreshDatabase;

    public function test_event_broadcasts_on_posts_channel_with_serialized_payload(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->for($user)->create([
            'content' => 'Moderation payload update',
            'moderation_status' => 'ok',
            'attachment_moderation_status' => 'ok',
            'attachment_is_blurred' => false,
        ]);

        $event = new PostUpdated($post);

        $this->assertSame('PostUpdated', $event->broadcastAs());

        $channels = $event->broadcastOn();
        $this->assertCount(1, $channels);
        $this->assertInstanceOf(Channel::class, $channels[0]);
        $this->assertSame('posts', $channels[0]->name);

        $payload = $event->broadcastWith();
        $this->assertSame($post->id, data_get($payload, 'post.id'));
        $this->assertSame('Moderation payload update', data_get($payload, 'post.content'));
        $this->assertSame('ok', data_get($payload, 'post.attachment_moderation_status'));
        $this->assertFalse((bool) data_get($payload, 'post.attachment_is_blurred'));
    }
}
