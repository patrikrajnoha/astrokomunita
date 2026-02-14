<?php

namespace Tests\Feature;

use App\Jobs\ModeratePostJob;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ModerationPostFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_posting_creates_pending_post_and_dispatches_moderation_job(): void
    {
        config()->set('moderation.enabled', true);

        $user = User::factory()->create();
        Sanctum::actingAs($user);
        Queue::fake();

        $response = $this->postJson('/api/posts', [
            'content' => 'Post for moderation queue',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('moderation_status', 'pending');

        $postId = (int) $response->json('id');

        $this->assertDatabaseHas('posts', [
            'id' => $postId,
            'moderation_status' => 'pending',
        ]);

        Queue::assertPushed(ModeratePostJob::class, function (ModeratePostJob $job) use ($postId) {
            return $job->postId === $postId;
        });
    }

    public function test_feed_excludes_blocked_and_hidden_posts(): void
    {
        $user = User::factory()->create();

        $visiblePost = Post::factory()->for($user)->create([
            'content' => 'Visible post',
            'moderation_status' => 'ok',
            'is_hidden' => false,
            'hidden_at' => null,
        ]);

        Post::factory()->for($user)->create([
            'content' => 'Blocked post',
            'moderation_status' => 'blocked',
            'is_hidden' => true,
            'hidden_at' => now(),
        ]);

        Post::factory()->for($user)->create([
            'content' => 'Hidden manually',
            'moderation_status' => 'ok',
            'is_hidden' => true,
            'hidden_at' => now(),
        ]);

        $response = $this->getJson('/api/feed?with=counts');

        $response->assertOk();
        $response->assertJsonPath('data.0.id', $visiblePost->id);
        $response->assertJsonCount(1, 'data');
    }
}
