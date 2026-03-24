<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeedVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_for_you_feed_excludes_bot_posts_including_pinned(): void
    {
        $user = User::factory()->create();
        $stellarbotUser = User::factory()->create([
            'name' => 'Stellar',
            'username' => 'stellarbot',
            'email' => null,
            'is_bot' => true,
            'role' => User::ROLE_BOT,
        ]);

        $userPost = Post::factory()->for($user)->create([
            'content' => 'Human post',
            'feed_key' => 'community',
            'author_kind' => 'user',
            'source_name' => null,
        ]);

        Post::factory()->for($stellarbotUser)->create([
            'content' => 'Bot post',
            'feed_key' => 'astro',
            'author_kind' => 'bot',
            'bot_identity' => 'stela',
            'source_name' => 'nasa_rss',
        ]);

        Post::factory()->for($stellarbotUser)->create([
            'content' => 'Pinned bot post',
            'feed_key' => 'astro',
            'author_kind' => 'bot',
            'bot_identity' => 'stela',
            'source_name' => 'wiki_onthisday',
            'pinned_at' => now(),
        ]);

        Post::factory()->for($stellarbotUser)->create([
            'content' => 'NASA RSS post',
            'feed_key' => 'astro',
            'author_kind' => 'bot',
            'bot_identity' => 'stela',
            'source_name' => 'nasa_rss',
        ]);

        $response = $this->getJson('/api/feed?with=counts');

        $response->assertOk();
        $response->assertJsonPath('data.0.id', $userPost->id);
        $response->assertJsonCount(1, 'data');
    }

    public function test_astro_feed_still_returns_only_bot_sources(): void
    {
        $user = User::factory()->create();
        $stellarbotUser = User::factory()->create([
            'name' => 'Stellar',
            'username' => 'stellarbot',
            'email' => null,
            'is_bot' => true,
            'role' => User::ROLE_BOT,
        ]);

        Post::factory()->for($user)->create([
            'content' => 'Human post',
            'feed_key' => 'community',
            'author_kind' => 'user',
            'source_name' => null,
        ]);

        $botPost = Post::factory()->for($stellarbotUser)->create([
            'content' => 'Bot post',
            'feed_key' => 'astro',
            'author_kind' => 'bot',
            'bot_identity' => 'stela',
            'source_name' => 'nasa_rss',
        ]);

        Post::factory()->for($stellarbotUser)->create([
            'content' => 'Wiki post',
            'feed_key' => 'astro',
            'author_kind' => 'bot',
            'bot_identity' => 'stela',
            'source_name' => 'wiki_onthisday',
        ]);

        $response = $this->getJson('/api/astro-feed?with=counts');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $response->assertJsonFragment(['id' => $botPost->id]);
        $response->assertJsonMissing(['content' => 'Human post']);

        $botRow = collect($response->json('data'))
            ->first(fn (array $row): bool => (int) ($row['id'] ?? 0) === (int) $botPost->id);

        $this->assertIsArray($botRow);
        $this->assertSame('bots/stellarbot/sb_blue.png', (string) data_get($botRow, 'user.avatar_path'));
        $this->assertStringContainsString(
            '/api/bot-avatars/stellarbot/sb_blue.png',
            (string) data_get($botRow, 'user.avatar_url')
        );
    }
}
