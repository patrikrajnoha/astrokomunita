<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProfilePinningTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_pin_only_one_own_root_post_on_profile_and_it_is_sorted_first(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $older = Post::factory()->for($user)->create([
            'content' => 'Older post',
            'created_at' => Carbon::parse('2026-03-01 10:00:00'),
        ]);
        $newer = Post::factory()->for($user)->create([
            'content' => 'Newer post',
            'created_at' => Carbon::parse('2026-03-01 10:10:00'),
        ]);

        $this->patchJson("/api/profile/posts/{$older->id}/pin")
            ->assertOk()
            ->assertJsonPath('post.id', $older->id);

        $this->assertDatabaseHas('posts', [
            'id' => $older->id,
            'user_id' => $user->id,
        ]);
        $this->assertNotNull(Post::query()->whereKey($older->id)->value('profile_pinned_at'));
        $this->assertNull(Post::query()->whereKey($newer->id)->value('profile_pinned_at'));

        $this->patchJson("/api/profile/posts/{$newer->id}/pin")
            ->assertOk()
            ->assertJsonPath('post.id', $newer->id);

        $this->assertNull(Post::query()->whereKey($older->id)->value('profile_pinned_at'));
        $this->assertNotNull(Post::query()->whereKey($newer->id)->value('profile_pinned_at'));

        $this->getJson('/api/posts?scope=me&kind=roots&per_page=10')
            ->assertOk()
            ->assertJsonPath('data.0.id', $newer->id)
            ->assertJsonPath('data.1.id', $older->id);
    }

    public function test_user_cannot_pin_someone_elses_post_on_profile(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $otherPost = Post::factory()->for($other)->create();

        Sanctum::actingAs($user);

        $this->patchJson("/api/profile/posts/{$otherPost->id}/pin")
            ->assertForbidden();
    }

    public function test_profile_pin_rejects_reply_posts(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $root = Post::factory()->for($user)->create([
            'parent_id' => null,
            'root_id' => null,
            'depth' => 0,
        ]);
        $reply = Post::factory()->for($user)->create([
            'parent_id' => $root->id,
            'root_id' => $root->id,
            'depth' => 1,
        ]);

        $this->patchJson("/api/profile/posts/{$reply->id}/pin")
            ->assertStatus(422);
    }

    public function test_profile_pin_does_not_move_post_to_top_of_global_feed(): void
    {
        $firstUser = User::factory()->create();
        $secondUser = User::factory()->create();

        $profilePinnedCandidate = Post::factory()->for($firstUser)->create([
            'content' => 'Pinned on profile only',
            'created_at' => Carbon::parse('2026-03-01 09:00:00'),
        ]);
        $newestFeedPost = Post::factory()->for($secondUser)->create([
            'content' => 'Newest in feed',
            'created_at' => Carbon::parse('2026-03-01 10:00:00'),
        ]);

        Sanctum::actingAs($firstUser);
        $this->patchJson("/api/profile/posts/{$profilePinnedCandidate->id}/pin")->assertOk();

        $this->getJson('/api/feed?per_page=10')
            ->assertOk()
            ->assertJsonPath('data.0.id', $newestFeedPost->id);
    }

    public function test_admin_profile_pin_is_independent_from_global_feed_pin(): void
    {
        $admin = User::factory()->admin()->create();
        $author = User::factory()->create();

        $adminPost = Post::factory()->for($admin)->create([
            'content' => 'Admin post',
            'created_at' => Carbon::parse('2026-03-01 09:00:00'),
        ]);
        $newerPost = Post::factory()->for($author)->create([
            'content' => 'Newer community post',
            'created_at' => Carbon::parse('2026-03-01 10:00:00'),
        ]);

        Sanctum::actingAs($admin);

        $this->patchJson("/api/profile/posts/{$adminPost->id}/pin")->assertOk();

        $this->getJson('/api/feed?per_page=10')
            ->assertOk()
            ->assertJsonPath('data.0.id', $newerPost->id);

        $this->patchJson("/api/admin/posts/{$adminPost->id}/pin")
            ->assertOk();

        $this->getJson('/api/feed?per_page=10')
            ->assertOk()
            ->assertJsonPath('data.0.id', $adminPost->id);
    }
}

