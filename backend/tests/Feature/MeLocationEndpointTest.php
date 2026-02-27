<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MeLocationEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_updates_authenticated_users_precise_location(): void
    {
        $user = User::factory()->create([
            'location' => 'Bratislava',
            'location_label' => null,
            'location_source' => null,
            'latitude' => null,
            'longitude' => null,
            'timezone' => null,
        ]);

        $response = $this->actingAs($user)->putJson('/api/me/location', [
            'latitude' => 48.1486,
            'longitude' => 17.1077,
            'timezone' => 'Europe/Bratislava',
            'location_label' => 'Bratislava, SK',
            'location_source' => 'preset',
        ]);

        $response->assertOk()
            ->assertJsonPath('latitude', 48.1486)
            ->assertJsonPath('longitude', 17.1077)
            ->assertJsonPath('timezone', 'Europe/Bratislava')
            ->assertJsonPath('location', 'Bratislava, SK')
            ->assertJsonPath('location_label', 'Bratislava, SK')
            ->assertJsonPath('location_source', 'preset')
            ->assertJsonPath('location_data.latitude', 48.1486)
            ->assertJsonPath('location_data.longitude', 17.1077)
            ->assertJsonPath('location_data.timezone', 'Europe/Bratislava')
            ->assertJsonPath('location_data.label', 'Bratislava, SK')
            ->assertJsonPath('location_data.source', 'preset')
            ->assertJsonPath('location_meta.lat', 48.1486)
            ->assertJsonPath('location_meta.lon', 17.1077)
            ->assertJsonPath('location_meta.tz', 'Europe/Bratislava')
            ->assertJsonPath('location_meta.label', 'Bratislava, SK')
            ->assertJsonPath('location_meta.source', 'preset');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'location' => 'Bratislava, SK',
            'location_label' => 'Bratislava, SK',
            'location_source' => 'preset',
            'timezone' => 'Europe/Bratislava',
        ]);
    }

    public function test_it_rejects_invalid_timezone(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->putJson('/api/me/location', [
            'latitude' => 48.1486,
            'longitude' => 17.1077,
            'timezone' => 'Invalid/Timezone',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['timezone']);
    }

    public function test_it_rejects_coordinates_out_of_range(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->putJson('/api/me/location', [
            'latitude' => 120.1,
            'longitude' => -195.0,
            'timezone' => 'Europe/Bratislava',
            'location_source' => 'manual',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['latitude', 'longitude']);
    }

    public function test_it_rejects_invalid_location_source(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->putJson('/api/me/location', [
            'latitude' => 48.1486,
            'longitude' => 17.1077,
            'timezone' => 'Europe/Bratislava',
            'location_source' => 'other',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['location_source']);
    }
}
