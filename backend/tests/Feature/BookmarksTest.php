<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BookmarksTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_manage_or_list_bookmarks(): void
    {
        $post = Post::factory()->create();

        $this->postJson("/api/posts/{$post->id}/bookmark")->assertStatus(401);
        $this->deleteJson("/api/posts/{$post->id}/bookmark")->assertStatus(401);
        $this->getJson('/api/me/bookmarks')->assertStatus(401);
    }

    public function test_user_can_bookmark_and_repeated_post_is_idempotent(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->create();

        Sanctum::actingAs($user);

        $this->postJson("/api/posts/{$post->id}/bookmark")
            ->assertOk()
            ->assertJsonPath('is_bookmarked', true);

        $this->postJson("/api/posts/{$post->id}/bookmark")
            ->assertOk()
            ->assertJsonPath('is_bookmarked', true);

        $this->assertDatabaseHas('post_user_bookmarks', [
            'user_id' => $user->id,
            'post_id' => $post->id,
        ]);

        $count = DB::table('post_user_bookmarks')
            ->where('user_id', $user->id)
            ->where('post_id', $post->id)
            ->count();

        $this->assertSame(1, $count);
    }

    public function test_user_can_delete_bookmark_and_repeated_delete_is_idempotent(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->create();

        DB::table('post_user_bookmarks')->insert([
            'user_id' => $user->id,
            'post_id' => $post->id,
            'created_at' => now(),
        ]);

        Sanctum::actingAs($user);

        $this->deleteJson("/api/posts/{$post->id}/bookmark")
            ->assertOk()
            ->assertJsonPath('is_bookmarked', false);

        $this->deleteJson("/api/posts/{$post->id}/bookmark")
            ->assertOk()
            ->assertJsonPath('is_bookmarked', false);

        $this->assertDatabaseMissing('post_user_bookmarks', [
            'user_id' => $user->id,
            'post_id' => $post->id,
        ]);
    }

    public function test_bookmarks_index_returns_only_users_bookmarks_in_desc_order(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();

        $postOlder = Post::factory()->for($owner)->create();
        $postNewer = Post::factory()->for($owner)->create();
        $postOther = Post::factory()->for($owner)->create();

        DB::table('post_user_bookmarks')->insert([
            [
                'user_id' => $owner->id,
                'post_id' => $postOlder->id,
                'created_at' => now()->subMinutes(10),
            ],
            [
                'user_id' => $owner->id,
                'post_id' => $postNewer->id,
                'created_at' => now()->subMinutes(2),
            ],
            [
                'user_id' => $otherUser->id,
                'post_id' => $postOther->id,
                'created_at' => now()->subMinute(),
            ],
        ]);

        Sanctum::actingAs($owner);

        $response = $this->getJson('/api/me/bookmarks?per_page=10');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $response->assertJsonPath('data.0.id', $postNewer->id);
        $response->assertJsonPath('data.1.id', $postOlder->id);
        $response->assertJsonPath('data.0.is_bookmarked', true);
        $response->assertJsonPath('data.1.is_bookmarked', true);

        $this->assertNotNull(data_get($response->json(), 'data.0.bookmarked_at'));
    }

    public function test_bookmark_unique_constraint_rejects_duplicates(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->create();

        DB::table('post_user_bookmarks')->insert([
            'user_id' => $user->id,
            'post_id' => $post->id,
            'created_at' => now(),
        ]);

        $this->expectException(QueryException::class);

        DB::table('post_user_bookmarks')->insert([
            'user_id' => $user->id,
            'post_id' => $post->id,
            'created_at' => now(),
        ]);
    }
}
