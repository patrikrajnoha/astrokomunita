<?php

namespace Tests\Feature;

use App\Models\SidebarCustomComponent;
use App\Models\SidebarSectionConfig;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SidebarCustomComponentTest extends TestCase
{
    use RefreshDatabase;

    public function test_sidebar_config_resolves_custom_component_payload_for_runtime(): void
    {
        $component = SidebarCustomComponent::query()->create([
            'name' => 'Info card runtime',
            'type' => SidebarCustomComponent::TYPE_INFO_CARD,
            'is_active' => true,
            'config_json' => [
                'title' => 'Observing tip',
                'content' => 'Take a red flashlight.',
                'icon' => 'star',
            ],
        ]);

        SidebarSectionConfig::query()->create([
            'scope' => 'home',
            'kind' => 'custom_component',
            'section_key' => 'custom_component',
            'custom_component_id' => $component->id,
            'order' => 5,
            'is_enabled' => true,
        ]);

        $response = $this->getJson('/api/sidebar-config?scope=home');

        $response->assertOk()
            ->assertJsonFragment([
                'kind' => 'custom_component',
                'section_key' => 'custom_component',
                'custom_component_id' => $component->id,
                'type' => SidebarCustomComponent::TYPE_INFO_CARD,
            ])
            ->assertJsonFragment([
                'title' => 'Observing tip',
                'content' => 'Take a red flashlight.',
            ]);
    }

    public function test_admin_sidebar_custom_component_endpoints_are_removed(): void
    {
        Sanctum::actingAs(User::factory()->create([
            'role' => 'admin',
            'is_admin' => true,
        ]));

        $this->getJson('/api/admin/sidebar/custom-components')->assertStatus(404);
        $this->postJson('/api/admin/sidebar/custom-components', [])->assertStatus(404);
        $this->putJson('/api/admin/sidebar/custom-components/1', [])->assertStatus(404);
        $this->deleteJson('/api/admin/sidebar/custom-components/1')->assertStatus(404);
    }
}
