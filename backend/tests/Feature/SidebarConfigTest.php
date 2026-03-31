<?php

namespace Tests\Feature;

use App\Models\SidebarSectionConfig;
use App\Support\SidebarSectionRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SidebarConfigTest extends TestCase
{
    use RefreshDatabase;

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
            ->assertJsonCount(count(SidebarSectionRegistry::sections()), 'data')
            ->assertJsonPath('data.0.section_key', 'observing_conditions')
            ->assertJsonPath('data.0.is_enabled', true);
    }

    public function test_public_home_scope_returns_system_fallback_when_admin_has_not_configured_it(): void
    {
        $response = $this->getJson('/api/sidebar-config?scope=home');

        $response
            ->assertOk()
            ->assertJsonPath('scope', 'home')
            ->assertJsonCount(count(SidebarSectionRegistry::sections()), 'data')
            ->assertJsonPath('data.0.section_key', 'observing_conditions')
            ->assertJsonPath('data.0.is_enabled', true)
            ->assertJsonPath('data.3.section_key', 'iss_pass')
            ->assertJsonPath('data.3.is_enabled', true);
    }

    public function test_public_home_scope_uses_saved_admin_default_config_when_present(): void
    {
        SidebarSectionConfig::query()->create([
            'scope' => SidebarSectionRegistry::SCOPE_HOME,
            'kind' => 'builtin',
            'section_key' => 'search',
            'order' => 0,
            'is_enabled' => true,
        ]);
        SidebarSectionConfig::query()->create([
            'scope' => SidebarSectionRegistry::SCOPE_HOME,
            'kind' => 'builtin',
            'section_key' => 'nasa_apod',
            'order' => 1,
            'is_enabled' => true,
        ]);

        $response = $this->getJson('/api/sidebar-config?scope=home');

        $response
            ->assertOk()
            ->assertJsonPath('scope', 'home');

        $items = collect($response->json('data'));

        $this->assertTrue((bool) $items->firstWhere('section_key', 'search')['is_enabled']);
        $this->assertTrue((bool) $items->firstWhere('section_key', 'nasa_apod')['is_enabled']);
        $this->assertFalse((bool) $items->firstWhere('section_key', 'observing_conditions')['is_enabled']);
    }

    public function test_legacy_sidebar_sections_endpoint_is_removed(): void
    {
        $this->getJson('/api/sidebar-sections')->assertStatus(404);
    }
}
