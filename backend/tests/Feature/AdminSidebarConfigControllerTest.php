<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\SidebarSectionRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminSidebarConfigControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_sidebar_config_defaults_to_zero_enabled_when_unconfigured(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create([
            'email_verified_at' => now(),
            'is_active' => true,
        ]));

        $response = $this->getJson('/api/admin/sidebar-config');

        $response
            ->assertOk()
            ->assertJsonCount(count(SidebarSectionRegistry::sections()), 'data');

        $enabledCount = collect($response->json('data'))
            ->filter(static fn (array $item): bool => (bool) ($item['is_enabled'] ?? false))
            ->count();

        $this->assertSame(0, $enabledCount);
    }

    public function test_admin_sidebar_config_rejects_more_than_three_enabled_widgets(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create([
            'email_verified_at' => now(),
            'is_active' => true,
        ]));

        $sections = collect(SidebarSectionRegistry::sections())
            ->values()
            ->map(static fn (array $section, int $index): array => [
                'section_key' => $section['section_key'],
                'is_enabled' => $index < 4,
                'order' => $index,
            ])
            ->all();

        $response = $this->putJson('/api/admin/sidebar-config', [
            'sections' => $sections,
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['sections']);

        $this->assertDatabaseCount('sidebar_section_configs', 0);
    }

    public function test_admin_sidebar_config_accepts_three_enabled_widgets(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create([
            'email_verified_at' => now(),
            'is_active' => true,
        ]));

        $sections = collect(SidebarSectionRegistry::sections())
            ->values()
            ->map(static fn (array $section, int $index): array => [
                'section_key' => $section['section_key'],
                'is_enabled' => $index < 3,
                'order' => $index,
            ])
            ->all();

        $response = $this->putJson('/api/admin/sidebar-config', [
            'sections' => $sections,
        ]);

        $response->assertOk();

        $enabledCount = collect($response->json('data'))
            ->filter(static fn (array $item): bool => (bool) ($item['is_enabled'] ?? false))
            ->count();

        $this->assertSame(3, $enabledCount);
    }
}

