<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeedVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_for_you_feed_excludes_astrobot_posts_including_pinned(): void
    {
        $user = User::factory()->create();
        $astrobotUser = User::factory()->create([
            'name' => 'AstroBot',
            'username' => 'astrobot',
            'email' => 'astrobot@astrokomunita.local',
            'is_bot' => true,
        ]);

        $userPost = Post::factory()->for($user)->create([
            'content' => 'Human post',
            'source_name' => null,
        ]);

        Post::factory()->for($astrobotUser)->create([
            'content' => 'AstroBot post',
            'source_name' => 'astrobot',
        ]);

        Post::factory()->for($astrobotUser)->create([
            'content' => 'Pinned AstroBot post',
            'source_name' => 'astrobot',
            'pinned_at' => now(),
        ]);

        Post::factory()->for($astrobotUser)->create([
            'content' => 'NASA RSS post',
            'source_name' => 'nasa_rss',
        ]);

        $response = $this->getJson('/api/feed?with=counts');

        $response->assertOk();
        $response->assertJsonPath('data.0.id', $userPost->id);
        $response->assertJsonCount(1, 'data');
    }

    public function test_astrobot_feed_still_returns_only_astrobot_sources(): void
    {
        $user = User::factory()->create();
        $astrobotUser = User::factory()->create([
            'name' => 'AstroBot',
            'username' => 'astrobot',
            'email' => 'astrobot@astrokomunita.local',
            'is_bot' => true,
        ]);

        Post::factory()->for($user)->create([
            'content' => 'Human post',
            'source_name' => null,
        ]);

        $astrobotPost = Post::factory()->for($astrobotUser)->create([
            'content' => 'AstroBot post',
            'source_name' => 'astrobot',
        ]);

        Post::factory()->for($astrobotUser)->create([
            'content' => 'NASA RSS post',
            'source_name' => 'nasa_rss',
        ]);

        $response = $this->getJson('/api/feed/astrobot?with=counts');

        $response->assertOk();
        $response->assertJsonPath('data.0.id', $astrobotPost->id);
        $response->assertJsonCount(2, 'data');
    }
}

