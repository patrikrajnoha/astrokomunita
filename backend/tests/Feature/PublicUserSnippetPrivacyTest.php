<?php

namespace Tests\Feature;

use App\Models\Observation;
use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicUserSnippetPrivacyTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_user_snippet_endpoints_do_not_expose_private_user_fields(): void
    {
        $author = User::factory()->create([
            'name' => 'Public Author',
            'username' => 'publicauthor',
            'email' => 'public.author@example.com',
            'location' => 'Bratislava, SK',
            'location_label' => 'Bratislava, SK',
            'location_source' => 'preset',
            'latitude' => 48.1486,
            'longitude' => 17.1077,
            'timezone' => 'Europe/Bratislava',
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

        Observation::factory()->for($author)->create([
            'title' => 'Public observation',
            'is_public' => true,
        ]);

        $endpoints = [
            '/api/feed',
            '/api/tags/perseids',
            '/api/users/publicauthor/posts',
            '/api/users/publicauthor',
            '/api/observations?user_id=' . $author->id,
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
            $this->assertNotSame('location_data', (string) $key, "Unexpected location_data key found in response for {$endpoint}.");
            $this->assertNotSame('location_meta', (string) $key, "Unexpected location_meta key found in response for {$endpoint}.");

            if (is_array($value)) {
                $this->assertNoEmailKeyRecursive($value, $endpoint);
            }
        }
    }
}
