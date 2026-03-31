<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use App\Models\UserPreference;
use App\Services\Location\IpLocationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
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
            'role' => User::ROLE_EDITOR,
            'is_admin' => false,
            'is_bot' => false,
            'location' => 'Kosice, SK',
            'created_at' => now()->subDays(2),
        ]);
        User::factory()->create([
            'role' => 'user',
            'is_admin' => false,
            'is_bot' => true,
            'location' => 'Praha, CZ',
            'created_at' => now()->subDays(1),
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

        $expectedEditorCount = (int) DB::table('users')
            ->where('role', User::ROLE_EDITOR)
            ->where('is_bot', false)
            ->count();

        Sanctum::actingAs($admin);
        Cache::flush();

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
                    'by_region_active_ip_30d',
                ],
                'trend' => [
                    'range_days',
                    'points',
                ],
                'generated_at',
            ])
            ->assertJsonPath('demographics.by_role.editor', $expectedEditorCount)
            ->assertJsonPath('trend.range_days', 30)
            ->assertJson(fn ($json) => $json
                ->whereType('kpi.users_total', 'integer')
                ->whereType('kpi.users_active_30d', 'integer')
                ->whereType('kpi.posts_total', 'integer')
                ->whereType('kpi.events_total', 'integer')
                ->whereType('kpi.posts_moderated_total', 'integer')
                ->whereType('demographics.by_region_active_ip_30d.unknown', 'integer')
                ->whereType('demographics.by_region_active_ip_30d.sk', 'integer')
                ->whereType('demographics.by_region_active_ip_30d.cz', 'integer')
                ->whereType('demographics.by_region_active_ip_30d.other', 'integer')
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

    public function test_ip_region_breakdown_does_not_classify_slovenia_as_slovakia(): void
    {
        config([
            'admin.stats_ip_region_enabled' => true,
            'admin.stats_ip_region_lookup_max_per_build' => 10,
        ]);
        Cache::flush();

        $admin = User::factory()->create([
            'role' => 'admin',
            'is_admin' => true,
            'is_active' => true,
            'last_login_at' => null,
        ]);
        $activeUser = User::factory()->create([
            'role' => 'user',
            'is_admin' => false,
            'is_active' => true,
            'last_login_at' => now(),
        ]);

        DB::table('sessions')->insert([
            'id' => 'stats-slovenia-session',
            'user_id' => $activeUser->id,
            'ip_address' => '193.77.150.1',
            'user_agent' => 'phpunit',
            'payload' => 'test',
            'last_activity' => now()->timestamp,
        ]);

        $mock = \Mockery::mock(IpLocationService::class);
        $mock->shouldReceive('lookup')
            ->once()
            ->with('193.77.150.1')
            ->andReturn([
                'country' => 'Slovenia',
                'city' => 'Ljubljana',
                'approx_lat' => 46.0569,
                'approx_lon' => 14.5058,
                'timezone' => 'Europe/Ljubljana',
            ]);
        $this->app->instance(IpLocationService::class, $mock);

        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/stats')
            ->assertOk()
            ->assertJsonPath('demographics.by_region_active_ip_30d.sk', 0)
            ->assertJsonPath('demographics.by_region_active_ip_30d.other', 1);
    }

    public function test_ip_region_breakdown_limits_external_lookups_per_build(): void
    {
        config([
            'admin.stats_ip_region_enabled' => true,
            'admin.stats_ip_region_lookup_max_per_build' => 1,
        ]);
        Cache::flush();

        $admin = User::factory()->create([
            'role' => 'admin',
            'is_admin' => true,
            'is_active' => true,
            'last_login_at' => null,
        ]);
        $firstActive = User::factory()->create([
            'role' => 'user',
            'is_admin' => false,
            'is_active' => true,
            'last_login_at' => now(),
        ]);
        $secondActive = User::factory()->create([
            'role' => 'user',
            'is_admin' => false,
            'is_active' => true,
            'last_login_at' => now(),
        ]);

        DB::table('sessions')->insert([
            [
                'id' => 'stats-budget-session-1',
                'user_id' => $firstActive->id,
                'ip_address' => '93.184.216.34',
                'user_agent' => 'phpunit',
                'payload' => 'test',
                'last_activity' => now()->timestamp,
            ],
            [
                'id' => 'stats-budget-session-2',
                'user_id' => $secondActive->id,
                'ip_address' => '8.8.8.8',
                'user_agent' => 'phpunit',
                'payload' => 'test',
                'last_activity' => now()->timestamp,
            ],
        ]);

        $mock = \Mockery::mock(IpLocationService::class);
        $mock->shouldReceive('lookup')
            ->once()
            ->andReturn([
                'country' => 'Slovakia',
                'city' => 'Bratislava',
                'approx_lat' => 48.1486,
                'approx_lon' => 17.1077,
                'timezone' => 'Europe/Bratislava',
            ]);
        $this->app->instance(IpLocationService::class, $mock);

        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/stats')
            ->assertOk()
            ->assertJsonPath('demographics.by_region_active_ip_30d.sk', 1)
            ->assertJsonPath('demographics.by_region_active_ip_30d.unknown', 1);
    }

    public function test_profile_region_breakdown_maps_slovak_village_to_sk_using_timezone(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_admin' => true,
            'is_active' => true,
            'location' => null,
            'location_label' => null,
            'timezone' => null,
        ]);

        User::factory()->create([
            'role' => 'user',
            'is_admin' => false,
            'is_active' => true,
            'location' => 'Liptovska Luzna',
            'location_label' => 'Liptovska Luzna',
            'timezone' => 'Europe/Bratislava',
        ]);

        Sanctum::actingAs($admin);
        Cache::flush();

        $this->getJson('/api/admin/stats')
            ->assertOk()
            ->assertJsonPath('demographics.by_region.sk', 1)
            ->assertJsonPath('demographics.by_region.unknown', 1);
    }

    public function test_profile_region_breakdown_falls_back_to_user_preferences_location(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_admin' => true,
            'is_active' => true,
            'location' => null,
            'location_label' => null,
            'timezone' => null,
        ]);

        $userWithPreferenceLocation = User::factory()->create([
            'role' => 'user',
            'is_admin' => false,
            'is_active' => true,
            'location' => null,
            'location_label' => null,
            'timezone' => null,
            'latitude' => null,
            'longitude' => null,
        ]);

        UserPreference::query()->create([
            'user_id' => $userWithPreferenceLocation->id,
            'region' => 'global',
            'location_label' => 'Slovensko',
            'location_lat' => 48.1486,
            'location_lon' => 17.1077,
        ]);

        Sanctum::actingAs($admin);
        Cache::flush();

        $this->getJson('/api/admin/stats')
            ->assertOk()
            ->assertJsonPath('demographics.by_region.sk', 1)
            ->assertJsonPath('demographics.by_region.unknown', 1);
    }

    public function test_profile_region_breakdown_excludes_bot_accounts(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_admin' => true,
            'is_active' => true,
            'is_bot' => false,
            'location' => null,
            'location_label' => null,
            'timezone' => null,
        ]);

        User::factory()->create([
            'role' => 'user',
            'is_admin' => false,
            'is_active' => true,
            'is_bot' => false,
            'location' => 'Praha, CZ',
            'location_label' => 'Praha, CZ',
            'timezone' => 'Europe/Prague',
        ]);

        User::factory()->create([
            'role' => 'bot',
            'is_admin' => false,
            'is_active' => true,
            'is_bot' => true,
            'location' => 'Kosice, SK',
            'location_label' => 'Kosice, SK',
            'timezone' => 'Europe/Bratislava',
        ]);

        Sanctum::actingAs($admin);
        Cache::flush();

        $this->getJson('/api/admin/stats')
            ->assertOk()
            ->assertJsonPath('demographics.by_region.cz', 1)
            ->assertJsonPath('demographics.by_region.sk', 0);
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
