<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AstroFeedFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_community_feed_endpoint_excludes_astro_posts(): void
    {
        $human = User::factory()->create();
        $bot = User::factory()->create([
            'username' => 'stela',
            'is_bot' => true,
        ]);

        $communityPost = Post::factory()->for($human)->create([
            'feed_key' => 'community',
            'author_kind' => 'user',
            'content' => 'Community root post',
        ]);

        $astroPost = Post::factory()->for($bot)->create([
            'feed_key' => 'astro',
            'author_kind' => 'bot',
            'bot_identity' => 'stela',
            'content' => 'Astro root post',
        ]);

        $response = $this->getJson('/api/feed?with=counts');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->all();

        $this->assertContains($communityPost->id, $ids);
        $this->assertNotContains($astroPost->id, $ids);
        $this->assertTrue(
            collect($response->json('data'))->every(fn (array $row): bool => ($row['feed_key'] ?? null) === 'community')
        );
    }

    public function test_astro_feed_endpoint_returns_only_astro_posts(): void
    {
        $human = User::factory()->create();
        $bot = User::factory()->create([
            'username' => 'stela',
            'is_bot' => true,
        ]);

        Post::factory()->for($human)->create([
            'feed_key' => 'community',
            'author_kind' => 'user',
            'content' => 'Community post',
        ]);

        $astroPost = Post::factory()->for($bot)->create([
            'feed_key' => 'astro',
            'author_kind' => 'bot',
            'bot_identity' => 'stela',
            'content' => 'Astro post',
        ]);

        $response = $this->getJson('/api/astro-feed?with=counts');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($astroPost->id, $ids);
        $this->assertTrue(
            collect($response->json('data'))->every(
                fn (array $row): bool => ($row['feed_key'] ?? null) === 'astro' && ($row['author_kind'] ?? null) === 'bot'
            )
        );
    }

    public function test_user_can_comment_on_bot_post(): void
    {
        $bot = User::factory()->create([
            'username' => 'stela',
            'is_bot' => true,
        ]);
        $commenter = User::factory()->create();

        $astroPost = Post::factory()->for($bot)->create([
            'feed_key' => 'astro',
            'author_kind' => 'bot',
            'bot_identity' => 'stela',
            'content' => 'Bot post to comment',
            'parent_id' => null,
            'root_id' => null,
            'depth' => 0,
        ]);

        Sanctum::actingAs($commenter);

        $response = $this->postJson("/api/posts/{$astroPost->id}/reply", [
            'content' => 'Comment on bot post',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('parent_id', $astroPost->id);
        $response->assertJsonPath('feed_key', 'astro');
        $response->assertJsonPath('author_kind', 'user');

        $this->assertDatabaseHas('posts', [
            'id' => $response->json('id'),
            'parent_id' => $astroPost->id,
            'feed_key' => 'astro',
            'author_kind' => 'user',
        ]);
    }

    public function test_user_cannot_create_astro_root_post_even_if_feed_key_is_astro(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/posts', [
            'content' => 'Attempt to spoof astro root',
            'feed_key' => 'astro',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['author_kind']);
        $this->assertDatabaseMissing('posts', ['content' => 'Attempt to spoof astro root']);
    }

    public function test_user_cannot_spoof_bot_author_kind_or_bot_identity(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $authorKindSpoof = $this->postJson('/api/posts', [
            'content' => 'Attempt bot spoof',
            'author_kind' => 'bot',
            'bot_identity' => 'kozmo',
            'feed_key' => 'astro',
        ]);

        $authorKindSpoof->assertStatus(422);
        $authorKindSpoof->assertJsonValidationErrors(['author_kind']);

        $botIdentitySpoof = $this->postJson('/api/posts', [
            'content' => 'Attempt bot identity spoof',
            'bot_identity' => 'kozmo',
        ]);

        $botIdentitySpoof->assertStatus(422);
        $botIdentitySpoof->assertJsonValidationErrors(['bot_identity']);
        $this->assertDatabaseMissing('posts', ['content' => 'Attempt bot spoof']);
        $this->assertDatabaseMissing('posts', ['content' => 'Attempt bot identity spoof']);
    }

    public function test_reply_ignores_feed_key_from_request_and_inherits_parent_feed(): void
    {
        $bot = User::factory()->create([
            'username' => 'stela',
            'is_bot' => true,
        ]);
        $user = User::factory()->create();

        $astroPost = Post::factory()->for($bot)->create([
            'feed_key' => 'astro',
            'author_kind' => 'bot',
            'bot_identity' => 'stela',
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson("/api/posts/{$astroPost->id}/reply", [
            'content' => 'Reply must inherit parent feed',
            'feed_key' => 'community',
            'author_kind' => 'bot',
            'bot_identity' => 'kozmo',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('feed_key', 'astro');
        $response->assertJsonPath('author_kind', 'user');
        $response->assertJsonPath('bot_identity', null);
    }

    public function test_long_kozmo_content_passes_validation_and_is_stored_fully(): void
    {
        $kozmo = User::factory()->create([
            'username' => 'kozmo',
            'email' => 'kozmo@astrokomunita.local',
            'is_bot' => true,
        ]);

        Sanctum::actingAs($kozmo);

        $longContent = implode("\n", array_fill(
            0,
            700,
            'Kozmo long content line for validation and storage length assertion.'
        ));

        $response = $this->postJson('/api/posts', [
            'content' => $longContent,
            'author_kind' => 'bot',
            'bot_identity' => 'kozmo',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('feed_key', 'astro');
        $response->assertJsonPath('author_kind', 'bot');
        $response->assertJsonPath('bot_identity', 'kozmo');

        $postId = (int) $response->json('id');
        $stored = Post::query()->findOrFail($postId);

        $this->assertSame(mb_strlen($longContent), mb_strlen((string) $stored->content));
        $this->assertSame('astro', (string) $stored->feed_key->value);
        $this->assertSame('bot', (string) $stored->author_kind->value);
        $this->assertSame('kozmo', (string) $stored->bot_identity?->value);
    }

    public function test_regular_user_cannot_delete_bot_post(): void
    {
        $bot = User::factory()->create([
            'username' => 'stela',
            'is_bot' => true,
        ]);
        $regular = User::factory()->create();

        $botPost = Post::factory()->for($bot)->create([
            'feed_key' => 'astro',
            'author_kind' => 'bot',
            'bot_identity' => 'stela',
        ]);

        Sanctum::actingAs($regular);

        $this->deleteJson("/api/posts/{$botPost->id}")->assertStatus(403);
        $this->assertDatabaseHas('posts', ['id' => $botPost->id]);
    }
}
