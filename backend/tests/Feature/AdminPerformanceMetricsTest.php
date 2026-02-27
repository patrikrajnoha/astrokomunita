<?php

namespace Tests\Feature;

use App\Models\PerformanceLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminPerformanceMetricsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_get_performance_metrics(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_admin' => true,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/admin/performance-metrics');

        $response
            ->assertOk()
            ->assertJsonStructure([
                'logs',
                'last_run_per_key',
                'trend',
            ]);
    }

    public function test_non_admin_gets_403(): void
    {
        $user = User::factory()->create([
            'role' => 'user',
            'is_admin' => false,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/admin/performance-metrics')
            ->assertStatus(403);
    }

    public function test_run_events_list_creates_performance_log(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_admin' => true,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        Sanctum::actingAs($admin);

        $this->postJson('/api/admin/performance-metrics/run', [
            'run' => 'events_list',
            'sample_size' => 3,
            'mode' => 'normal',
        ])->assertOk()
            ->assertJsonPath('status', 'ok');

        $this->assertDatabaseCount('performance_logs', 1);
        $this->assertTrue(
            PerformanceLog::query()->where('key', 'events_list_3')->exists()
        );
    }
}
