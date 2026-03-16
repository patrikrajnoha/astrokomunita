<?php

namespace Tests\Feature;

use App\Models\Hashtag;
use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicPostPayloadPrivacyTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_post_endpoints_do_not_expose_private_storage_or_moderation_payloads(): void
    {
        $author = User::factory()->create([
            'name' => 'Sky Watcher',
            'username' => 'skywatcher',
        ]);

        $post = Post::factory()->create([
            'user_id' => $author->id,
            'content' => 'Mars and Perseids tonight',
            'parent_id' => null,
            'is_hidden' => false,
            'hidden_at' => null,
            'moderation_status' => 'ok',
            'moderation_summary' => [
                'text' => ['toxicity_score' => 0.01],
                'combined_decision' => 'ok',
            ],
            'attachment_path' => 'posts/1/images/1/web.webp',
            'attachment_web_path' => 'posts/1/images/1/web.webp',
            'attachment_original_path' => 'posts/1/images/1/original.jpg',
            'attachment_mime' => 'image/webp',
            'attachment_web_mime' => 'image/webp',
            'attachment_original_mime' => 'image/jpeg',
            'attachment_original_name' => 'mars.jpg',
            'attachment_original_size' => 4096,
            'attachment_web_size' => 1024,
            'attachment_variants_json' => [
                'original' => ['path' => 'posts/1/images/1/original.jpg'],
                'web' => ['path' => 'posts/1/images/1/web.webp'],
            ],
            'attachment_moderation_status' => 'ok',
            'attachment_moderation_summary' => [
                'decision' => 'ok',
                'nsfw_score' => 0.01,
            ],
            'source_uid' => 'source-private-uid',
            'bot_item_id' => null,
            'expires_at' => null,
        ]);

        $tag = Tag::query()->create(['name' => 'perseids']);
        $hashtag = Hashtag::query()->create(['name' => 'mars']);
        $post->tags()->attach($tag->id);
        $post->hashtags()->attach($hashtag->id);

        $endpoints = [
            '/api/feed',
            '/api/tags/perseids',
            '/api/users/skywatcher/posts',
            '/api/search/posts?q=Mars',
            '/api/search/global?q=Mars',
            '/api/hashtags/mars/posts',
        ];

        foreach ($endpoints as $endpoint) {
            $response = $this->getJson($endpoint)->assertOk();
            $payload = $response->json();
            $this->assertIsArray($payload);
            $this->assertNoPrivatePostKeysRecursive($payload, $endpoint);
        }
    }

    private function assertNoPrivatePostKeysRecursive(array $payload, string $endpoint): void
    {
        $forbiddenKeys = [
            'attachment_original_path',
            'attachment_web_path',
            'attachment_original_mime',
            'attachment_web_mime',
            'attachment_original_size',
            'attachment_variants_json',
            'attachment_moderation_summary',
            'moderation_summary',
            'source_uid',
            'bot_item_id',
            'hidden_reason',
            'hidden_at',
        ];

        foreach ($payload as $key => $value) {
            $this->assertNotContains(
                (string) $key,
                $forbiddenKeys,
                "Unexpected private post key [{$key}] found in response for {$endpoint}."
            );

            if (is_array($value)) {
                $this->assertNoPrivatePostKeysRecursive($value, $endpoint);
            }
        }
    }
}
