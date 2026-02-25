<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class JsonUtf8ResponseTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_password_error_response_is_json_and_contains_slovak_diacritics(): void
    {
        $user = User::factory()->create([
            'password' => 'CurrentPass123!',
        ]);

        Sanctum::actingAs($user);

        $response = $this->patchJson('/api/profile/password', [
            'current_password' => 'wrong-password',
            'password' => 'NewSecurePass123!',
            'password_confirmation' => 'NewSecurePass123!',
        ]);

        $response->assertStatus(422);
        $response->assertHeader('Content-Type');
        $this->assertStringContainsString(
            'application/json',
            (string) $response->headers->get('Content-Type')
        );

        $message = (string) $response->json('message');
        $this->assertSame("Aktu\u{00E1}lne heslo nie je spr\u{00E1}vne.", $message);
        $this->assertMatchesRegularExpression('/[\x{00E1}\x{00E4}\x{010D}\x{010F}\x{00E9}\x{00ED}\x{013A}\x{013E}\x{0148}\x{00F3}\x{00F4}\x{0155}\x{0161}\x{0165}\x{00FA}\x{00FD}\x{017E}]/u', $message);
    }
}