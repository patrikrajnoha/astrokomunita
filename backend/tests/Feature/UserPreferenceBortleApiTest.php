<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserPreferenceBortleApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_put_rejects_bortle_class_below_range(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->putJson('/api/me/preferences', [
            'bortle_class' => 0,
        ])->assertStatus(422);
    }

    public function test_put_rejects_bortle_class_above_range(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->putJson('/api/me/preferences', [
            'bortle_class' => 10,
        ])->assertStatus(422);
    }

    public function test_put_persists_valid_bortle_class(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->putJson('/api/me/preferences', [
            'bortle_class' => 6,
        ])->assertOk()
            ->assertJsonPath('data.bortle_class', 6);

        $this->assertDatabaseHas('user_preferences', [
            'user_id' => $user->id,
            'bortle_class' => 6,
        ]);
    }
}
