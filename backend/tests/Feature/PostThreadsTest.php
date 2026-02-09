<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PostThreadsTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_root_post_sets_thread_fields(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/posts', [
            'content' => 'Root post',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('parent_id', null);
        $response->assertJsonPath('root_id', null);
        $response->assertJsonPath('depth', 0);

        $this->assertDatabaseHas('posts', [
            'content' => 'Root post',
            'parent_id' => null,
            'root_id' => null,
            'depth' => 0,
        ]);
    }

    public function test_create_reply_depth_one_sets_parent_root_and_depth(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $root = Post::create([
            'user_id' => $user->id,
            'content' => 'Root post',
            'parent_id' => null,
            'root_id' => null,
            'depth' => 0,
        ]);

        $response = $this->postJson("/api/posts/{$root->id}/reply", [
            'content' => 'Reply depth 1',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('parent_id', $root->id);
        $response->assertJsonPath('root_id', $root->id);
        $response->assertJsonPath('depth', 1);

        $this->assertDatabaseHas('posts', [
            'content' => 'Reply depth 1',
            'parent_id' => $root->id,
            'root_id' => $root->id,
            'depth' => 1,
        ]);
    }

    public function test_create_reply_depth_two_sets_parent_root_and_depth(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $root = Post::create([
            'user_id' => $user->id,
            'content' => 'Root post',
            'parent_id' => null,
            'root_id' => null,
            'depth' => 0,
        ]);

        $reply = Post::create([
            'user_id' => $user->id,
            'content' => 'Reply depth 1',
            'parent_id' => $root->id,
            'root_id' => $root->id,
            'depth' => 1,
        ]);

        $response = $this->postJson("/api/posts/{$reply->id}/reply", [
            'content' => 'Reply depth 2',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('parent_id', $reply->id);
        $response->assertJsonPath('root_id', $root->id);
        $response->assertJsonPath('depth', 2);

        $this->assertDatabaseHas('posts', [
            'content' => 'Reply depth 2',
            'parent_id' => $reply->id,
            'root_id' => $root->id,
            'depth' => 2,
        ]);
    }

    public function test_reply_depth_three_returns_422(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $root = Post::create([
            'user_id' => $user->id,
            'content' => 'Root post',
            'parent_id' => null,
            'root_id' => null,
            'depth' => 0,
        ]);

        $reply = Post::create([
            'user_id' => $user->id,
            'content' => 'Reply depth 1',
            'parent_id' => $root->id,
            'root_id' => $root->id,
            'depth' => 1,
        ]);

        $replyDepth2 = Post::create([
            'user_id' => $user->id,
            'content' => 'Reply depth 2',
            'parent_id' => $reply->id,
            'root_id' => $root->id,
            'depth' => 2,
        ]);

        $response = $this->postJson("/api/posts/{$replyDepth2->id}/reply", [
            'content' => 'Reply depth 3',
        ]);

        $response->assertStatus(422);
    }

    public function test_index_returns_only_root_posts(): void
    {
        $user = User::factory()->create();

        $root = Post::create([
            'user_id' => $user->id,
            'content' => 'Root post',
            'parent_id' => null,
            'root_id' => null,
            'depth' => 0,
        ]);

        Post::create([
            'user_id' => $user->id,
            'content' => 'Reply depth 1',
            'parent_id' => $root->id,
            'root_id' => $root->id,
            'depth' => 1,
        ]);

        $response = $this->getJson('/api/posts');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $root->id);
        $response->assertJsonPath('data.0.parent_id', null);
    }

    public function test_show_returns_thread_data(): void
    {
        $user = User::factory()->create();

        $root = Post::create([
            'user_id' => $user->id,
            'content' => 'Root post',
            'parent_id' => null,
            'root_id' => null,
            'depth' => 0,
        ]);

        $reply = Post::create([
            'user_id' => $user->id,
            'content' => 'Reply depth 1',
            'parent_id' => $root->id,
            'root_id' => $root->id,
            'depth' => 1,
        ]);

        Post::create([
            'user_id' => $user->id,
            'content' => 'Reply depth 2',
            'parent_id' => $reply->id,
            'root_id' => $root->id,
            'depth' => 2,
        ]);

        $response = $this->getJson("/api/posts/{$reply->id}");

        $response->assertOk();
        $response->assertJsonPath('post.id', $reply->id);
        $response->assertJsonPath('root.id', $root->id);
        $response->assertJsonCount(3, 'thread');
        $response->assertJsonPath('thread.0.id', $root->id);
        $response->assertJsonPath('thread.0.depth', 0);
    }

    public function test_index_with_counts_returns_replies_count(): void
    {
        $user = User::factory()->create();

        $root = Post::create([
            'user_id' => $user->id,
            'content' => 'Root post',
            'parent_id' => null,
            'root_id' => null,
            'depth' => 0,
        ]);

        Post::create([
            'user_id' => $user->id,
            'content' => 'Reply depth 1',
            'parent_id' => $root->id,
            'root_id' => $root->id,
            'depth' => 1,
        ]);

        $response = $this->getJson('/api/posts?with=counts');

        $response->assertOk();
        $response->assertJsonPath('data.0.id', $root->id);
        $response->assertJsonPath('data.0.replies_count', 1);
    }

    public function test_scope_me_requires_authentication(): void
    {
        $response = $this->getJson('/api/posts?scope=me');

        $response->assertStatus(401);
    }

    public function test_scope_me_accepts_sanctum_token_and_returns_only_my_posts(): void
    {
        $me = User::factory()->create();
        $other = User::factory()->create();

        Post::create([
            'user_id' => $me->id,
            'content' => 'My post',
            'parent_id' => null,
            'root_id' => null,
            'depth' => 0,
        ]);

        Post::create([
            'user_id' => $other->id,
            'content' => 'Other post',
            'parent_id' => null,
            'root_id' => null,
            'depth' => 0,
        ]);

        Sanctum::actingAs($me);

        $response = $this->getJson('/api/posts?scope=me');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.user_id', $me->id);
    }
}
