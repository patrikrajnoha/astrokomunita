<?php

namespace Tests\Feature;

use App\Models\SidebarSectionConfig;
use App\Models\User;
use App\Support\SidebarSectionRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserPreferenceSidebarWidgetsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_returns_empty_sidebar_widget_overrides_for_user_without_widget_selection(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/me/preferences')
            ->assertOk()
            ->assertJsonPath('data.has_preferences', false)
            ->assertJsonPath('data.sidebar_widget_keys', [])
            ->assertJsonPath('data.sidebar_widget_overrides', []);
    }

    public function test_put_rejects_more_than_three_sidebar_widgets(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->putJson('/api/me/preferences', [
            'sidebar_widget_keys' => ['search', 'nasa_apod', 'next_event', 'latest_articles'],
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['sidebar_widget_keys']);
    }

    public function test_put_persists_sidebar_widget_keys_and_returns_supported_sidebar_widgets(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->putJson('/api/me/preferences', [
            'sidebar_widget_keys' => ['search', 'nasa_apod', 'search'],
        ])->assertOk()
            ->assertJsonPath('data.sidebar_widget_keys', ['search', 'nasa_apod'])
            ->assertJsonPath('data.sidebar_widget_overrides.home', ['search', 'nasa_apod'])
            ->assertJsonPath('meta.supported_sidebar_widgets.0.section_key', 'observing_conditions');

        $this->getJson('/api/me/preferences')
            ->assertOk()
            ->assertJsonPath('data.sidebar_widget_keys', ['search', 'nasa_apod'])
            ->assertJsonPath('data.sidebar_widget_overrides.home', ['search', 'nasa_apod'])
            ->assertJsonPath('meta.supported_sidebar_widgets.0.section_key', 'observing_conditions');

        $preferences = $user->fresh()->eventPreference;
        $this->assertNotNull($preferences);
        $this->assertSame(['search', 'nasa_apod'], $preferences->normalizedSidebarWidgetKeys());
    }

    public function test_put_persists_sidebar_widget_overrides_per_scope(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->putJson('/api/me/preferences', [
            'sidebar_widget_overrides' => [
                'home' => ['search', 'nasa_apod', 'next_event'],
                'events' => ['upcoming_events', 'latest_articles'],
            ],
        ])->assertOk()
            ->assertJsonPath('data.sidebar_widget_keys', ['search', 'nasa_apod', 'next_event'])
            ->assertJsonPath('data.sidebar_widget_overrides.home', ['search', 'nasa_apod', 'next_event'])
            ->assertJsonPath('data.sidebar_widget_overrides.events', ['upcoming_events', 'latest_articles'])
            ->assertJsonPath('meta.supported_sidebar_scopes.0', 'home');

        $preferences = $user->fresh()->eventPreference;
        $this->assertNotNull($preferences);
        $this->assertSame(
            ['search', 'nasa_apod', 'next_event'],
            $preferences->normalizedSidebarWidgetKeys('home')
        );
        $this->assertSame(
            ['upcoming_events', 'latest_articles'],
            $preferences->normalizedSidebarWidgetKeys('events')
        );
    }

    public function test_explicit_empty_home_sidebar_override_is_preserved(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->putJson('/api/me/preferences', [
            'sidebar_widget_overrides' => [
                'home' => [],
            ],
        ])->assertOk()
            ->assertJsonPath('data.sidebar_widget_keys', [])
            ->assertJsonPath('data.sidebar_widget_overrides.home', []);

        $this->getJson('/api/me/preferences')
            ->assertOk()
            ->assertJsonPath('data.sidebar_widget_keys', [])
            ->assertJsonPath('data.sidebar_widget_overrides.home', []);
    }

    public function test_put_rejects_sidebar_widget_overrides_with_invalid_scope(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->putJson('/api/me/preferences', [
            'sidebar_widget_overrides' => [
                'bad_scope' => ['search'],
            ],
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['sidebar_widget_overrides']);
    }

    public function test_admin_can_persist_sidebar_widget_overrides_via_me_preferences(): void
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $this->putJson('/api/me/preferences', [
            'sidebar_widget_overrides' => [
                'home' => ['search', 'nasa_apod'],
            ],
        ])->assertOk()
            ->assertJsonPath('data.sidebar_widget_overrides.home', ['search', 'nasa_apod'])
            ->assertJsonPath('data.has_preferences', true);

        $this->getJson('/api/me/preferences')
            ->assertOk()
            ->assertJsonPath('data.sidebar_widget_overrides.home', ['search', 'nasa_apod']);
    }

    public function test_user_sidebar_override_does_not_mutate_admin_default_sidebar_config(): void
    {
        SidebarSectionConfig::query()->create([
            'scope' => SidebarSectionRegistry::SCOPE_HOME,
            'kind' => 'builtin',
            'section_key' => 'latest_articles',
            'order' => 0,
            'is_enabled' => true,
        ]);

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->putJson('/api/me/preferences', [
            'sidebar_widget_overrides' => [
                'home' => ['search', 'nasa_apod'],
            ],
        ])->assertOk()
            ->assertJsonPath('data.sidebar_widget_overrides.home', ['search', 'nasa_apod']);

        $this->assertSame(1, SidebarSectionConfig::query()->count());
        $this->assertDatabaseHas('sidebar_section_configs', [
            'scope' => SidebarSectionRegistry::SCOPE_HOME,
            'kind' => 'builtin',
            'section_key' => 'latest_articles',
            'is_enabled' => true,
            'order' => 0,
        ]);
    }
}
