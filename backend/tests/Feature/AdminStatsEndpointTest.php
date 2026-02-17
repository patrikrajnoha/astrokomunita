<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminStatsEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_access_stats_endpoint(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_admin' => true,
            'is_active' => true,
        ]);

        $regularUser = User::factory()->create([
            'role' => 'user',
            'is_admin' => false,
            'is_bot' => false,
            'location' => null,
            'created_at' => now()->subDays(5),
        ]);
        User::factory()->create([
            'role' => 'admin',
            'is_admin' => true,
            'location' => 'Bratislava, SK',
            'created_at' => now()->subDays(3),
        ]);
        User::factory()->create([
            'role' => 'user',
            'is_admin' => false,
            'is_bot' => true,
            'location' => 'Praha, CZ',
            'created_at' => now()->subDays(2),
        ]);

        Post::factory()->for($regularUser)->create([
            'moderation_status' => 'flagged',
            'created_at' => now()->subDays(1),
        ]);
        Post::factory()->for($regularUser)->create([
            'moderation_status' => 'ok',
            'created_at' => now(),
        ]);

        DB::table('events')->insert([
            'title' => 'Stats Event',
            'type' => 'meteor-shower',
            'start_at' => now()->addDay(),
            'end_at' => now()->addDays(2),
            'max_at' => now()->addDay(),
            'short' => 'Short',
            'description' => 'Description',
            'visibility' => 1,
            'source_name' => 'manual',
            'source_uid' => 'stats-event-1',
            'source_hash' => 'stats-event-hash-1',
            'created_at' => now(),
            'updated_at' => now(),
            'region_scope' => 'global',
        ]);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/admin/stats');

        $response
            ->assertOk()
            ->assertJsonStructure([
                'kpi' => [
                    'users_total',
                    'users_active_30d',
                    'posts_total',
                    'events_total',
                    'posts_moderated_total',
                ],
                'demographics' => [
                    'by_role',
                    'by_region',
                ],
                'trend' => [
                    'range_days',
                    'points',
                ],
                'generated_at',
            ])
            ->assertJsonPath('trend.range_days', 30)
            ->assertJson(fn ($json) => $json
                ->whereType('kpi.users_total', 'integer')
                ->whereType('kpi.users_active_30d', 'integer')
                ->whereType('kpi.posts_total', 'integer')
                ->whereType('kpi.events_total', 'integer')
                ->whereType('kpi.posts_moderated_total', 'integer')
                ->etc()
            );
    }

    public function test_non_admin_gets_403(): void
    {
        $user = User::factory()->create([
            'role' => 'user',
            'is_admin' => false,
            'is_active' => true,
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/admin/stats')
            ->assertStatus(403);
    }

    public function test_export_returns_csv_with_headers(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_admin' => true,
            'is_active' => true,
        ]);

        Sanctum::actingAs($admin);

        $response = $this->get('/api/admin/stats/export?format=csv');

        $response
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8')
            ->assertHeader('content-disposition');
    }
}
