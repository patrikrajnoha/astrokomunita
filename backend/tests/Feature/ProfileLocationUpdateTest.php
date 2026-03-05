<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileLocationUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_location_label_change_maps_known_preset_to_canonical_coordinates(): void
    {
        $user = User::factory()->create([
            'location' => 'Miami',
            'location_label' => 'Miami',
            'location_source' => 'manual',
            'latitude' => 25.7617,
            'longitude' => -80.1918,
            'timezone' => 'America/New_York',
        ]);

        $this->actingAs($user)
            ->patchJson('/api/profile', [
                'location' => 'Nitra',
            ])
            ->assertOk()
            ->assertJsonPath('location', 'Nitra')
            ->assertJsonPath('location_label', 'Nitra')
            ->assertJsonPath('latitude', 48.3064)
            ->assertJsonPath('longitude', 18.0764)
            ->assertJsonPath('timezone', 'Europe/Bratislava')
            ->assertJsonPath('location_source', 'preset');

        $user->refresh();
        $this->assertSame(48.3064, round((float) $user->latitude, 4));
        $this->assertSame(18.0764, round((float) $user->longitude, 4));
        $this->assertSame('Europe/Bratislava', $user->timezone);
        $this->assertSame('preset', $user->location_source);

        $this->actingAs($user)
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('location_data.label', 'Nitra')
            ->assertJsonPath('location_data.latitude', 48.3064)
            ->assertJsonPath('location_data.longitude', 18.0764)
            ->assertJsonPath('location_data.timezone', 'Europe/Bratislava')
            ->assertJsonPath('location_data.source', 'preset');
    }

    public function test_profile_location_label_change_to_unknown_value_clears_stale_coordinates(): void
    {
        $user = User::factory()->create([
            'location' => 'Miami',
            'location_label' => 'Miami',
            'location_source' => 'manual',
            'latitude' => 25.7617,
            'longitude' => -80.1918,
            'timezone' => 'America/New_York',
        ]);

        $this->actingAs($user)
            ->patchJson('/api/profile', [
                'location' => 'Neznama lokalita',
            ])
            ->assertOk()
            ->assertJsonPath('location', 'Neznama lokalita')
            ->assertJsonPath('location_label', 'Neznama lokalita');

        $user->refresh();
        $this->assertNull($user->latitude);
        $this->assertNull($user->longitude);
        $this->assertNull($user->timezone);
        $this->assertNull($user->location_source);
    }
}
