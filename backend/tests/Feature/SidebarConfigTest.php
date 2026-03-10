<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\SidebarSectionRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SidebarConfigTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_get_returns_default_config_when_database_is_empty(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_admin' => true,
        ]);
        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/admin/sidebar-config?scope=home');

        $defaultSectionsCount = count(SidebarSectionRegistry::sections());

        $response
            ->assertOk()
            ->assertJsonPath('scope', 'home')
            ->assertJsonCount($defaultSectionsCount, 'data')
            ->assertJsonPath('data.0.section_key', 'observing_conditions')
            ->assertJsonPath('data.0.order', 0)
            ->assertJsonPath('data.0.is_enabled', true);
    }

    public function test_put_and_get_roundtrip_for_scope_configuration(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_admin' => true,
        ]);
        Sanctum::actingAs($admin);

        $payload = [
            'items' => [
                ['kind' => 'builtin', 'section_key' => 'latest_articles', 'order' => 0, 'is_enabled' => true],
                ['kind' => 'builtin', 'section_key' => 'search', 'order' => 2, 'is_enabled' => false],
                ['kind' => 'builtin', 'section_key' => 'nasa_apod', 'order' => 1, 'is_enabled' => true],
            ],
        ];

        $putResponse = $this->putJson('/api/admin/sidebar-config?scope=events', $payload);
        $putResponse->assertOk()->assertJsonPath('scope', 'events');

        $getResponse = $this->getJson('/api/admin/sidebar-config?scope=events');

        $getResponse
            ->assertOk()
            ->assertJsonFragment([
                'section_key' => 'latest_articles',
                'is_enabled' => true,
            ])
            ->assertJsonFragment([
                'section_key' => 'nasa_apod',
                'is_enabled' => true,
            ])
            ->assertJsonFragment([
                'section_key' => 'search',
                'is_enabled' => false,
            ]);

        $this->assertDatabaseHas('sidebar_section_configs', [
            'scope' => 'events',
            'section_key' => 'search',
            'is_enabled' => false,
        ]);
    }

    public function test_admin_get_invalid_scope_falls_back_to_home(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_admin' => true,
        ]);
        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/admin/sidebar-config?scope=unknown');

        $response
            ->assertOk()
            ->assertJsonPath('scope', 'home')
            ->assertJsonCount(count(SidebarSectionRegistry::sections()), 'data');
    }

    public function test_public_search_scope_returns_200_with_valid_json_structure(): void
    {
        $response = $this->getJson('/api/sidebar-config?scope=search');

        $response
            ->assertOk()
            ->assertJsonPath('scope', 'search')
            ->assertJsonStructure([
                'scope',
                'data' => [
                    '*' => [
                        'kind',
                        'section_key',
                        'title',
                        'custom_component_id',
                        'custom_component',
                        'order',
                        'is_enabled',
                    ],
                ],
            ])
            ->assertJsonCount(count(SidebarSectionRegistry::sections()), 'data');
    }

    public function test_public_sidebar_config_falls_back_to_home_for_invalid_scope(): void
    {
        $response = $this->getJson('/api/sidebar-config?scope=unknown');

        $response
            ->assertOk()
            ->assertJsonPath('scope', 'home')
            ->assertJsonCount(count(SidebarSectionRegistry::sections()), 'data');
    }

    public function test_admin_invalid_scope_returns_422_on_put(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_admin' => true,
        ]);
        Sanctum::actingAs($admin);

        $response = $this->putJson('/api/admin/sidebar-config?scope=unknown', [
            'items' => [
                ['kind' => 'builtin', 'section_key' => 'search', 'order' => 0, 'is_enabled' => true],
            ],
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['scope']);
    }

    public function test_unknown_section_key_returns_422_on_put(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_admin' => true,
        ]);
        Sanctum::actingAs($admin);

        $response = $this->putJson('/api/admin/sidebar-config?scope=home', [
            'items' => [
                ['kind' => 'builtin', 'section_key' => 'unknown_widget', 'order' => 0, 'is_enabled' => true],
            ],
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['items.0.section_key']);
    }

    public function test_admin_put_rejects_more_than_three_enabled_widgets(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_admin' => true,
        ]);
        Sanctum::actingAs($admin);

        $response = $this->putJson('/api/admin/sidebar-config?scope=home', [
            'items' => [
                ['kind' => 'builtin', 'section_key' => 'search', 'order' => 0, 'is_enabled' => true],
                ['kind' => 'builtin', 'section_key' => 'nasa_apod', 'order' => 1, 'is_enabled' => true],
                ['kind' => 'builtin', 'section_key' => 'next_event', 'order' => 2, 'is_enabled' => true],
                ['kind' => 'builtin', 'section_key' => 'latest_articles', 'order' => 3, 'is_enabled' => true],
                ['kind' => 'builtin', 'section_key' => 'observing_conditions', 'order' => 4, 'is_enabled' => false],
                ['kind' => 'builtin', 'section_key' => 'upcoming_events', 'order' => 5, 'is_enabled' => false],
            ],
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['items']);
    }

    public function test_admin_put_allows_observing_widgets_combination(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_admin' => true,
        ]);
        Sanctum::actingAs($admin);

        $response = $this->putJson('/api/admin/sidebar-config?scope=home', [
            'items' => [
                ['kind' => 'builtin', 'section_key' => 'observing_conditions', 'order' => 0, 'is_enabled' => true],
                ['kind' => 'builtin', 'section_key' => 'observing_weather', 'order' => 1, 'is_enabled' => true],
                ['kind' => 'builtin', 'section_key' => 'night_sky', 'order' => 2, 'is_enabled' => true],
                ['kind' => 'builtin', 'section_key' => 'search', 'order' => 3, 'is_enabled' => false],
                ['kind' => 'builtin', 'section_key' => 'nasa_apod', 'order' => 4, 'is_enabled' => false],
                ['kind' => 'builtin', 'section_key' => 'next_event', 'order' => 5, 'is_enabled' => false],
                ['kind' => 'builtin', 'section_key' => 'latest_articles', 'order' => 6, 'is_enabled' => false],
                ['kind' => 'builtin', 'section_key' => 'upcoming_events', 'order' => 7, 'is_enabled' => false],
            ],
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('scope', 'home')
            ->assertJsonFragment([
                'section_key' => 'observing_conditions',
                'is_enabled' => true,
            ])
            ->assertJsonFragment([
                'section_key' => 'observing_weather',
                'is_enabled' => true,
            ])
            ->assertJsonFragment([
                'section_key' => 'night_sky',
                'is_enabled' => true,
            ]);
    }

    public function test_post_detail_scope_can_be_configured(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_admin' => true,
        ]);
        Sanctum::actingAs($admin);

        $putResponse = $this->putJson('/api/admin/sidebar-config?scope=post_detail', [
            'items' => [
                ['kind' => 'builtin', 'section_key' => 'search', 'order' => 0, 'is_enabled' => false],
                ['kind' => 'builtin', 'section_key' => 'latest_articles', 'order' => 1, 'is_enabled' => true],
            ],
        ]);

        $putResponse
            ->assertOk()
            ->assertJsonPath('scope', 'post_detail');

        $getResponse = $this->getJson('/api/admin/sidebar-config?scope=post_detail');

        $getResponse
            ->assertOk()
            ->assertJsonPath('scope', 'post_detail')
            ->assertJsonFragment([
                'section_key' => 'search',
                'is_enabled' => false,
            ]);
    }

    public function test_article_detail_scope_can_be_configured(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_admin' => true,
        ]);
        Sanctum::actingAs($admin);

        $putResponse = $this->putJson('/api/admin/sidebar-config?scope=article_detail', [
            'items' => [
                ['kind' => 'builtin', 'section_key' => 'search', 'order' => 0, 'is_enabled' => true],
                ['kind' => 'builtin', 'section_key' => 'latest_articles', 'order' => 1, 'is_enabled' => false],
            ],
        ]);

        $putResponse
            ->assertOk()
            ->assertJsonPath('scope', 'article_detail');

        $getResponse = $this->getJson('/api/admin/sidebar-config?scope=article_detail');

        $getResponse
            ->assertOk()
            ->assertJsonPath('scope', 'article_detail')
            ->assertJsonFragment([
                'section_key' => 'latest_articles',
                'is_enabled' => false,
            ]);
    }

    public function test_profile_scope_can_be_configured(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_admin' => true,
        ]);
        Sanctum::actingAs($admin);

        $putResponse = $this->putJson('/api/admin/sidebar-config?scope=profile', [
            'items' => [
                ['kind' => 'builtin', 'section_key' => 'search', 'order' => 0, 'is_enabled' => true],
                ['kind' => 'builtin', 'section_key' => 'upcoming_events', 'order' => 1, 'is_enabled' => false],
            ],
        ]);

        $putResponse
            ->assertOk()
            ->assertJsonPath('scope', 'profile');

        $getResponse = $this->getJson('/api/admin/sidebar-config?scope=profile');

        $getResponse
            ->assertOk()
            ->assertJsonPath('scope', 'profile')
            ->assertJsonFragment([
                'section_key' => 'upcoming_events',
                'is_enabled' => false,
            ]);
    }

    public function test_legacy_sky_scope_falls_back_to_home(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_admin' => true,
        ]);
        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/admin/sidebar-config?scope=sky');

        $response
            ->assertOk()
            ->assertJsonPath('scope', 'home')
            ->assertJsonCount(count(SidebarSectionRegistry::sections()), 'data');
    }

    public function test_settings_scope_is_valid_and_returns_config(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_admin' => true,
        ]);
        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/admin/sidebar-config?scope=settings');

        $response
            ->assertOk()
            ->assertJsonPath('scope', 'settings')
            ->assertJsonCount(count(SidebarSectionRegistry::sections()), 'data');
    }

    public function test_observing_scope_is_valid_and_returns_config(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_admin' => true,
        ]);
        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/admin/sidebar-config?scope=observing');

        $response
            ->assertOk()
            ->assertJsonPath('scope', 'observing')
            ->assertJsonCount(count(SidebarSectionRegistry::sections()), 'data');
    }
}
