<?php

namespace Tests\Feature;

use App\Enums\RegionScope;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OnboardingPreferencesTest extends TestCase
{
    use RefreshDatabase;

    public function test_preferences_can_store_and_return_onboarding_fields(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $completedAt = Carbon::parse('2026-02-17T10:00:00+00:00');

        $this->putJson('/api/me/preferences', [
            'region' => RegionScope::Global->value,
            'interests' => ['meteory', 'komety', 'meteory'],
            'location_label' => 'Bratislava, Slovensko',
            'location_place_id' => 'sk:bratislava',
            'location_lat' => 48.1486,
            'location_lon' => 17.1077,
            'onboarding_completed_at' => $completedAt->toIso8601String(),
        ])->assertOk()
            ->assertJsonPath('data.interests', ['meteory', 'komety'])
            ->assertJsonPath('data.location_label', 'Bratislava, Slovensko')
            ->assertJsonPath('data.location_place_id', 'sk:bratislava')
            ->assertJsonPath('data.location_lat', 48.1486)
            ->assertJsonPath('data.location_lon', 17.1077)
            ->assertJsonPath('data.onboarding_completed_at', $completedAt->toIso8601String());

        $this->getJson('/api/me/preferences')
            ->assertOk()
            ->assertJsonPath('data.interests', ['meteory', 'komety'])
            ->assertJsonPath('data.location_label', 'Bratislava, Slovensko')
            ->assertJsonPath('data.location_place_id', 'sk:bratislava')
            ->assertJsonPath('data.location_lat', 48.1486)
            ->assertJsonPath('data.location_lon', 17.1077)
            ->assertJsonPath('data.onboarding_completed_at', $completedAt->toIso8601String());
    }

    public function test_admin_user_preferences_can_be_saved_and_read(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
            'role' => 'admin',
        ]);
        Sanctum::actingAs($admin);

        $completedAt = Carbon::parse('2026-02-17T10:00:00+00:00');

        $this->putJson('/api/me/preferences', [
            'region' => RegionScope::Global->value,
            'interests' => ['meteory'],
            'onboarding_completed_at' => $completedAt->toIso8601String(),
        ])->assertOk()
            ->assertJsonPath('data.has_preferences', true)
            ->assertJsonPath('data.interests', ['meteory'])
            ->assertJsonPath('data.onboarding_completed_at', $completedAt->toIso8601String());

        $this->getJson('/api/me/preferences')
            ->assertOk()
            ->assertJsonPath('data.has_preferences', true)
            ->assertJsonPath('data.onboarding_completed_at', $completedAt->toIso8601String())
            ->assertJsonPath('data.interests', ['meteory']);

        $this->assertDatabaseHas('user_preferences', [
            'user_id' => $admin->id,
        ]);
    }
}
