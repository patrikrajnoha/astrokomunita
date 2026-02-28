<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MeLocationAutoEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_ip_based_location_payload_for_authenticated_user(): void
    {
        $user = User::factory()->create();

        Http::fake([
            'https://ipwho.is/*' => Http::response([
                'success' => true,
                'country' => 'Slovakia',
                'city' => 'Bratislava',
                'latitude' => 48.1486,
                'longitude' => 17.1077,
                'timezone' => [
                    'id' => 'Europe/Bratislava',
                ],
            ], 200),
        ]);

        $response = $this->actingAs($user)
            ->withServerVariables(['REMOTE_ADDR' => '93.184.216.34'])
            ->getJson('/api/me/location/auto');

        $response->assertOk()
            ->assertJson([
                'country' => 'Slovakia',
                'city' => 'Bratislava',
                'approx_lat' => 48.1486,
                'approx_lon' => 17.1077,
                'timezone' => 'Europe/Bratislava',
            ]);
    }

    public function test_it_returns_fallback_payload_when_provider_fails(): void
    {
        $user = User::factory()->create();

        Http::fake([
            'https://ipwho.is/*' => Http::response(['success' => false], 503),
        ]);

        $response = $this->actingAs($user)->getJson('/api/me/location/auto');

        $response->assertOk()
            ->assertJsonStructure([
                'country',
                'city',
                'approx_lat',
                'approx_lon',
                'timezone',
            ]);
    }

    public function test_guest_is_blocked_with_401(): void
    {
        $this->getJson('/api/me/location/auto')->assertStatus(401);
    }
}
