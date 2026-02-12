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
}
