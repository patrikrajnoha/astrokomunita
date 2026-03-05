<?php

namespace Tests\Feature;

use App\Models\Hashtag;
use App\Models\Post;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HashtagTrendingTest extends TestCase
{
    use RefreshDatabase;

    public function test_trending_returns_ranked_hashtags_with_counts_for_last_24_hours(): void
    {
        $user = User::factory()->create();
        $alpha = Hashtag::create(['name' => 'alpha']);
        $beta = Hashtag::create(['name' => 'beta']);
        $gamma = Hashtag::create(['name' => 'gamma']);

        $this->createPostWithHashtag($user, $alpha, CarbonImmutable::now()->subHours(3));
        $this->createPostWithHashtag($user, $alpha, CarbonImmutable::now()->subHours(6));
        $this->createPostWithHashtag($user, $beta, CarbonImmutable::now()->subHours(4));
        $this->createPostWithHashtag($user, $gamma, CarbonImmutable::now()->subDays(2));

        $root = $this->createPostWithHashtag($user, $alpha, CarbonImmutable::now()->subHours(2));
        $reply = Post::factory()->for($user)->create([
            'parent_id' => $root->id,
            'root_id' => $root->id,
            'depth' => 1,
            'content' => 'reply',
            'created_at' => CarbonImmutable::now()->subHours(1),
            'updated_at' => CarbonImmutable::now()->subHours(1),
        ]);
        $reply->hashtags()->sync([$alpha->id]);

        $response = $this->getJson('/api/trending?limit=10');

        $response->assertOk();
        $payload = $response->json();

        $this->assertCount(2, $payload);
        $this->assertSame('alpha', $payload[0]['name']);
        $this->assertSame(3, $payload[0]['posts_count']);
        $this->assertSame(1, $payload[0]['rank']);
        $this->assertSame(24, $payload[0]['window_hours']);

        $this->assertSame('beta', $payload[1]['name']);
        $this->assertSame(1, $payload[1]['posts_count']);
        $this->assertSame(2, $payload[1]['rank']);
        $this->assertSame(24, $payload[1]['window_hours']);
    }

    private function createPostWithHashtag(User $user, Hashtag $hashtag, CarbonImmutable $createdAt): Post
    {
        $post = Post::factory()->for($user)->create([
            'content' => 'post #' . $hashtag->name,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);

        $post->hashtags()->sync([$hashtag->id]);

        return $post;
    }
}