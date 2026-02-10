<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostViewCountTest extends TestCase
{
    use RefreshDatabase;

    public function test_view_endpoint_increments_only_once_per_ip_within_ttl(): void
    {
        $author = User::factory()->create();
        $post = Post::factory()->for($author)->create(['views' => 0]);

        $this->withServerVariables(['REMOTE_ADDR' => '10.10.10.10'])
            ->postJson("/api/posts/{$post->id}/view")
            ->assertOk()
            ->assertJsonPath('incremented', true)
            ->assertJsonPath('views', 1);

        $this->withServerVariables(['REMOTE_ADDR' => '10.10.10.10'])
            ->postJson("/api/posts/{$post->id}/view")
            ->assertOk()
            ->assertJsonPath('incremented', false)
            ->assertJsonPath('views', 1);
    }

    public function test_view_endpoint_increments_for_different_ip(): void
    {
        $author = User::factory()->create();
        $post = Post::factory()->for($author)->create(['views' => 0]);

        $this->withServerVariables(['REMOTE_ADDR' => '10.10.10.10'])
            ->postJson("/api/posts/{$post->id}/view")
            ->assertOk()
            ->assertJsonPath('views', 1);

        $this->withServerVariables(['REMOTE_ADDR' => '10.10.10.11'])
            ->postJson("/api/posts/{$post->id}/view")
            ->assertOk()
            ->assertJsonPath('views', 2);
    }

    public function test_views_field_is_present_in_feed_and_post_detail_responses(): void
    {
        $author = User::factory()->create();
        $post = Post::factory()->for($author)->create([
            'views' => 7,
            'parent_id' => null,
        ]);

        $this->getJson('/api/feed')
            ->assertOk()
            ->assertJsonPath('data.0.id', $post->id)
            ->assertJsonPath('data.0.views', 7);

        $this->getJson("/api/posts/{$post->id}")
            ->assertOk()
            ->assertJsonPath('root.id', $post->id)
            ->assertJsonPath('root.views', 7);
    }
}

