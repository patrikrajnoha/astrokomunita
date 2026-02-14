<?php

namespace Tests\Feature;

use App\Models\User;
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

        $response
            ->assertOk()
            ->assertJsonPath('scope', 'home')
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('data.0.section_key', 'search')
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
            ->assertJsonPath('data.0.section_key', 'latest_articles')
            ->assertJsonPath('data.0.order', 0)
            ->assertJsonPath('data.0.is_enabled', true)
            ->assertJsonPath('data.1.section_key', 'nasa_apod')
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

    public function test_invalid_scope_returns_400(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_admin' => true,
        ]);
        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/admin/sidebar-config?scope=unknown');

        $response
            ->assertStatus(400)
            ->assertJsonPath('message', 'Invalid sidebar scope.');
    }

    public function test_unknown_section_key_returns_400_on_put(): void
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
            ->assertStatus(400)
            ->assertJsonPath('message', 'Unknown section_key provided.')
            ->assertJsonPath('section_key', 'unknown_widget');
    }
}
