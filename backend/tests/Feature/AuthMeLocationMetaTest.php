<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthMeLocationMetaTest extends TestCase
{
    use RefreshDatabase;

    public function test_auth_me_returns_location_meta_for_known_location(): void
    {
        $user = User::factory()->create([
            'location' => 'Bratislava, SK',
        ]);

        $response = $this->actingAs($user)->getJson('/api/auth/me');

        $response
            ->assertOk()
            ->assertJsonPath('location', 'Bratislava, SK')
            ->assertJsonPath('location_meta.lat', 48.1486)
            ->assertJsonPath('location_meta.lon', 17.1077)
            ->assertJsonPath('location_meta.tz', 'Europe/Bratislava');
    }
}

