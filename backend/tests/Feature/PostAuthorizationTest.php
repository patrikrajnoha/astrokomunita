<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PostAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_cannot_report_own_post(): void
    {
        $owner = User::factory()->create();
        $post = Post::factory()->for($owner)->create();

        Sanctum::actingAs($owner);

        $this->postJson('/api/reports', [
            'target_id' => $post->id,
            'reason' => 'spam',
            'message' => 'test',
        ])->assertStatus(403);

        $this->assertDatabaseCount('reports', 0);
    }

    public function test_user_can_report_other_users_post(): void
    {
        $owner = User::factory()->create();
        $reporter = User::factory()->create();
        $post = Post::factory()->for($owner)->create();

        Sanctum::actingAs($reporter);

        $this->postJson('/api/reports', [
            'target_id' => $post->id,
            'reason' => 'abuse',
            'message' => 'test',
        ])->assertCreated();

        $this->assertDatabaseHas('reports', [
            'reporter_user_id' => $reporter->id,
            'target_id' => $post->id,
            'target_type' => 'post',
            'reason' => 'abuse',
        ]);
    }

    public function test_non_owner_non_admin_cannot_delete_post(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $post = Post::factory()->for($owner)->create();

        Sanctum::actingAs($stranger);
        $this->deleteJson("/api/posts/{$post->id}")->assertStatus(403);

        $this->assertDatabaseHas('posts', ['id' => $post->id]);
    }

    public function test_owner_can_delete_own_post(): void
    {
        $owner = User::factory()->create();
        $post = Post::factory()->for($owner)->create();

        Sanctum::actingAs($owner);
        $this->deleteJson("/api/posts/{$post->id}")->assertNoContent();

        $this->assertDatabaseMissing('posts', ['id' => $post->id]);
    }

    public function test_admin_can_delete_other_users_post(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create([
            'is_admin' => true,
            'role' => 'admin',
        ]);
        $post = Post::factory()->for($owner)->create();

        Sanctum::actingAs($admin);
        $this->deleteJson("/api/posts/{$post->id}")->assertNoContent();

        $this->assertDatabaseMissing('posts', ['id' => $post->id]);
    }
}
