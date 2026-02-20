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
            'latitude' => null,
            'longitude' => null,
            'timezone' => null,
        ]);

        $response = $this->actingAs($user)->putJson('/api/me/location', [
            'latitude' => 48.1486,
            'longitude' => 17.1077,
            'timezone' => 'Europe/Bratislava',
            'location' => 'Bratislava, SK',
        ]);

        $response->assertOk()
            ->assertJsonPath('latitude', 48.1486)
            ->assertJsonPath('longitude', 17.1077)
            ->assertJsonPath('timezone', 'Europe/Bratislava')
            ->assertJsonPath('location_meta.lat', 48.1486)
            ->assertJsonPath('location_meta.lon', 17.1077)
            ->assertJsonPath('location_meta.tz', 'Europe/Bratislava');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
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
}
