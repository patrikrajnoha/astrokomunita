<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request as HttpRequest;
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

    public function test_user_cannot_report_bot_post(): void
    {
        $botOwner = User::factory()->bot()->create([
            'username' => 'kozmobot',
        ]);
        $reporter = User::factory()->create();
        $post = Post::factory()->for($botOwner)->create();

        Sanctum::actingAs($reporter);

        $this->postJson('/api/reports', [
            'target_id' => $post->id,
            'reason' => 'misinfo',
            'message' => 'translation issue',
        ])->assertStatus(422);

        $this->assertDatabaseCount('reports', 0);
    }

    public function test_user_can_report_other_users_post_with_post_id_payload(): void
    {
        $owner = User::factory()->create();
        $reporter = User::factory()->create();
        $post = Post::factory()->for($owner)->create();

        Sanctum::actingAs($reporter);

        $this->postJson('/api/reports', [
            'post_id' => $post->id,
            'reason' => 'misinfo',
            'message' => 'test',
        ])->assertCreated();

        $this->assertDatabaseHas('reports', [
            'reporter_user_id' => $reporter->id,
            'target_id' => $post->id,
            'target_type' => 'post',
            'reason' => 'misinfo',
        ]);
    }

    public function test_duplicate_report_is_rejected_without_creating_second_row(): void
    {
        $owner = User::factory()->create();
        $reporter = User::factory()->create();
        $post = Post::factory()->for($owner)->create();

        Sanctum::actingAs($reporter);

        $payload = [
            'target_id' => $post->id,
            'reason' => 'spam',
            'message' => 'duplicate',
        ];

        $this->postJson('/api/reports', $payload)->assertCreated();
        $this->postJson('/api/reports', $payload)
            ->assertStatus(409)
            ->assertJson([
                'status' => 'already_reported',
            ]);

        $this->assertSame(1, \App\Models\Report::query()
            ->where('reporter_user_id', $reporter->id)
            ->where('target_id', $post->id)
            ->count());
    }

    public function test_honeypot_report_payload_is_rejected(): void
    {
        $owner = User::factory()->create();
        $reporter = User::factory()->create();
        $post = Post::factory()->for($owner)->create();

        Sanctum::actingAs($reporter);

        $this->postJson('/api/reports', [
            'target_id' => $post->id,
            'reason' => 'spam',
            'message' => 'test',
            '_hp' => 'i am a bot',
        ])->assertStatus(422);

        $this->assertDatabaseCount('reports', 0);
    }

    public function test_reports_route_uses_named_report_submission_rate_limiter(): void
    {
        $route = app('router')->getRoutes()->match(HttpRequest::create('/api/reports', 'POST'));
        $this->assertContains('throttle:report-submissions', $route->gatherMiddleware());
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
