<?php

namespace Tests\Feature;

use Tests\TestCase;

class AdminRouteContractTest extends TestCase
{
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
}
