<?php

namespace Tests\Feature;

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
            ->assertJsonCount(count(SidebarSectionRegistry::sections()), 'data');
    }
}
