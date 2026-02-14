<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AuthLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_accepts_legacy_plaintext_password_and_rehashes(): void
    {
        $user = User::factory()->create([
            'email' => 'legacy@example.com',
            'password' => 'temporary-plain-password',
            'is_active' => true,
        ]);

        DB::table('users')
            ->where('id', $user->id)
            ->update(['password' => 'legacy-secret']);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'LEGACY@example.com',
            'password' => 'legacy-secret',
        ]);

        $response->assertOk();
        $this->assertAuthenticated();

        $user->refresh();
        $this->assertNotSame('legacy-secret', $user->password);
        $this->assertTrue(password_verify('legacy-secret', $user->password));
    }

    public function test_login_accepts_legacy_non_bcrypt_hash_and_rehashes(): void
    {
        if (! defined('PASSWORD_ARGON2ID')) {
            $this->markTestSkipped('Argon2id is not available in this PHP build.');
        }

        $user = User::factory()->create([
            'email' => 'legacy-hash@example.com',
            'password' => 'temporary-plain-password',
            'is_active' => true,
        ]);

        $argonHash = password_hash('legacy-hash-secret', PASSWORD_ARGON2ID);
        DB::table('users')
            ->where('id', $user->id)
            ->update(['password' => $argonHash]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'legacy-hash@example.com',
            'password' => 'legacy-hash-secret',
        ]);

        $response->assertOk();
        $this->assertAuthenticated();

        $user->refresh();
        $this->assertNotSame($argonHash, $user->password);
        $this->assertTrue(password_verify('legacy-hash-secret', $user->password));
    }
}
