<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserPostsRelationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_posts_relation_returns_post_collection(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        Post::factory()->for($user)->count(2)->create();
        Post::factory()->for($otherUser)->create();

        $posts = $user->posts;

        $this->assertInstanceOf(EloquentCollection::class, $posts);
        $this->assertCount(2, $posts);
        $this->assertTrue($posts->every(fn ($post) => $post instanceof Post));
    }

    public function test_recommendations_users_endpoint_works_with_posts_relation(): void
    {
        $viewer = User::factory()->create();
        $recommended = User::factory()->create();

        Post::factory()->for($recommended)->create([
            'parent_id' => null,
            'is_hidden' => false,
            'created_at' => now()->subDay(),
        ]);

        Sanctum::actingAs($viewer);

        $this->getJson('/api/recommendations/users?limit=5')
            ->assertOk()
            ->assertJsonFragment([
                'id' => $recommended->id,
                'username' => $recommended->username,
            ]);
    }
}
