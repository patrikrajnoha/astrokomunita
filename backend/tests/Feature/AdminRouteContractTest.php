<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminRouteContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_blog_posts_route_exists_and_is_protected(): void
    {
        $this->getJson('/api/admin/blog-posts')
            ->assertStatus(401);
    }

    public function test_admin_blog_posts_ai_suggest_tags_route_exists_and_is_protected(): void
    {
        $this->postJson('/api/admin/blog-posts/1/ai/suggest-tags')
            ->assertStatus(401);
    }

    public function test_admin_event_candidates_route_exists_and_is_protected(): void
    {
        $this->getJson('/api/admin/event-candidates')
            ->assertStatus(401);
    }

    public function test_admin_event_candidates_meta_route_exists_and_is_protected(): void
    {
        $this->getJson('/api/admin/event-candidates-meta')
            ->assertStatus(401);
    }

    public function test_admin_newsletter_preview_route_exists_and_is_protected(): void
    {
        $this->getJson('/api/admin/newsletter/preview')
            ->assertStatus(401);
    }

    public function test_admin_newsletter_preview_send_route_exists_and_is_protected(): void
    {
        $this->postJson('/api/admin/newsletter/preview', [
            'email' => 'preview@example.com',
        ])->assertStatus(401);
    }

    public function test_admin_newsletter_ai_draft_copy_route_exists_and_is_protected(): void
    {
        $this->postJson('/api/admin/newsletter/ai/draft-copy')
            ->assertStatus(401);
    }

    public function test_admin_ai_config_route_exists_and_is_protected(): void
    {
        $this->getJson('/api/admin/ai/config')
            ->assertStatus(401);
    }

    public function test_admin_event_ai_generate_route_exists_and_is_protected(): void
    {
        $this->postJson('/api/admin/events/1/ai/generate-description')
            ->assertStatus(401);
    }

    public function test_non_admin_user_cannot_access_admin_dashboard(): void
    {
        $user = User::factory()->create([
            'is_admin' => false,
            'role' => 'user',
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/admin/dashboard')
            ->assertStatus(403);
    }

    public function test_editor_can_access_content_admin_newsletter_and_events_index(): void
    {
        $editor = User::factory()->create([
            'is_admin' => false,
            'role' => 'editor',
            'email_verified_at' => now(),
        ]);

        $this->actingAs($editor, 'sanctum')
            ->getJson('/api/admin/newsletter/preview')
            ->assertOk();

        $this->actingAs($editor, 'sanctum')
            ->getJson('/api/admin/events')
            ->assertOk();
    }
}
