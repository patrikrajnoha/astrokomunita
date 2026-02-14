<?php

namespace Tests\Feature;

use App\Models\BlogPost;
use App\Models\Event;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GuestAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_access_public_feed_events_and_blog(): void
    {
        $author = User::factory()->create();

        Post::factory()->for($author)->create([
            'content' => 'Public feed post',
            'source_name' => null,
            'is_hidden' => false,
        ]);

        Event::query()->create([
            'title' => 'Public event',
            'type' => 'meteor-shower',
            'start_at' => now()->addDay(),
            'end_at' => now()->addDay()->addHour(),
            'max_at' => now()->addDay()->addHour(),
            'short' => 'Short summary',
            'description' => 'Description',
            'visibility' => 1,
            'source_name' => 'manual',
            'source_uid' => 'manual-guest-1',
            'source_hash' => 'manual-guest-hash-1',
        ]);

        BlogPost::query()->create([
            'user_id' => $author->id,
            'title' => 'Public article',
            'slug' => 'public-article',
            'content' => 'Published content',
            'published_at' => now()->subMinute(),
        ]);

        $feed = $this->getJson('/api/feed');
        $events = $this->getJson('/api/events');
        $blog = $this->getJson('/api/blog-posts');

        $feed->assertOk()->assertJsonStructure(['data']);
        $events->assertOk()->assertJsonStructure(['data']);
        $blog->assertOk()->assertJsonStructure(['data']);
    }

    public function test_public_feed_enriches_payload_for_authenticated_user(): void
    {
        $author = User::factory()->create();
        $viewer = User::factory()->create();

        $post = Post::factory()->for($author)->create([
            'source_name' => null,
            'is_hidden' => false,
        ]);

        DB::table('post_likes')->insert([
            'user_id' => $viewer->id,
            'post_id' => $post->id,
            'created_at' => now(),
        ]);

        Sanctum::actingAs($viewer);

        $response = $this->getJson('/api/feed?with=counts');

        $response->assertOk();
        $response->assertJsonPath('data.0.id', $post->id);
        $response->assertJsonPath('data.0.liked_by_me', true);
    }

    public function test_auth_only_endpoints_are_still_protected_for_guests(): void
    {
        $this->getJson('/api/favorites')->assertStatus(401);
        $this->getJson('/api/notifications')->assertStatus(401);
        $this->postJson('/api/posts', ['content' => 'Unauthorized'])->assertStatus(401);
    }
}
