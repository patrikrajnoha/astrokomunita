<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicUserSnippetPrivacyTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_user_snippet_endpoints_do_not_expose_email_key(): void
    {
        $author = User::factory()->create([
            'name' => 'Public Author',
            'username' => 'publicauthor',
            'email' => 'public.author@example.com',
        ]);

        $post = Post::factory()->create([
            'user_id' => $author->id,
            'content' => 'Mars and Perseids tonight',
            'parent_id' => null,
            'is_hidden' => false,
            'hidden_at' => null,
            'moderation_status' => 'ok',
            'expires_at' => null,
        ]);

        $tag = Tag::query()->create([
            'name' => 'perseids',
        ]);
        $post->tags()->attach($tag->id);

        $endpoints = [
            '/api/feed',
            '/api/tags/perseids',
            '/api/users/publicauthor/posts',
            '/api/search/users?q=pub',
            '/api/search/posts?q=Mars',
        ];

        foreach ($endpoints as $endpoint) {
            $response = $this->getJson($endpoint)->assertOk();
            $payload = $response->json();
            $this->assertIsArray($payload);
            $this->assertNoEmailKeyRecursive($payload, $endpoint);
        }
    }

    private function assertNoEmailKeyRecursive(array $payload, string $endpoint): void
    {
        foreach ($payload as $key => $value) {
            $this->assertNotSame('email', (string) $key, "Unexpected email key found in response for {$endpoint}.");

            if (is_array($value)) {
                $this->assertNoEmailKeyRecursive($value, $endpoint);
            }
        }
    }
}
