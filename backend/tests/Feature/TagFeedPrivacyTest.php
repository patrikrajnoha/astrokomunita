<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TagFeedPrivacyTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_tag_feed_does_not_expose_author_email(): void
    {
        $author = User::factory()->create([
            'email' => 'author@example.com',
        ]);

        $tag = Tag::query()->create([
            'name' => 'perseids',
        ]);

        $post = Post::factory()->create([
            'user_id' => $author->id,
            'moderation_status' => 'approved',
            'is_hidden' => false,
            'hidden_at' => null,
            'expires_at' => null,
        ]);

        $post->tags()->attach($tag->id);

        $response = $this->getJson('/api/tags/perseids')->assertOk();

        $posts = $response->json('posts.data');
        $this->assertIsArray($posts);
        $this->assertNotEmpty($posts);

        foreach ($posts as $item) {
            $this->assertArrayHasKey('user', $item);
            $this->assertIsArray($item['user']);
            $this->assertArrayNotHasKey('email', $item['user']);
        }
    }
}
