<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AuthLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_does_not_update_last_login_at(): void
    {
        $user = User::factory()->create([
            'email' => 'fresh-login@example.com',
            'password' => 'password',
            'last_login_at' => null,
            'is_active' => true,
        ]);

        $this->postJson('/api/auth/login', [
            'email' => 'fresh-login@example.com',
            'password' => 'password',
        ])->assertOk();

        $user->refresh();

        $this->assertNull($user->last_login_at);
    }

    public function test_login_rejects_legacy_plaintext_password_when_fallback_disabled(): void
    {
        $user = User::factory()->create([
            'email' => 'legacy-disabled@example.com',
            'password' => 'temporary-plain-password',
            'is_active' => true,
        ]);

        DB::table('users')
            ->where('id', $user->id)
            ->update(['password' => 'legacy-secret']);

        $this->postJson('/api/auth/login', [
            'email' => 'legacy-disabled@example.com',
            'password' => 'legacy-secret',
        ])->assertStatus(422);

        $this->assertGuest();
    }

    public function test_login_accepts_legacy_plaintext_password_without_updating_user_record(): void
    {
        config()->set('auth.legacy_plaintext_enabled', true);
        config()->set('auth.legacy_plaintext_allow_non_local', true);

        $user = User::factory()->create([
            'email' => 'legacy@example.com',
            'password' => 'temporary-plain-password',
            'remember_token' => null,
            'is_active' => true,
        ]);

        DB::table('users')
            ->where('id', $user->id)
            ->update(['password' => 'legacy-secret']);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'LEGACY@example.com',
            'password' => 'legacy-secret',
            'remember' => true,
        ]);

        $response->assertOk();
        $this->assertAuthenticated();

        $user->refresh();
        $this->assertSame('legacy-secret', $user->password);
        $this->assertNull($user->remember_token);
        $this->assertNull($user->last_login_at);
    }

    public function test_login_accepts_supported_hash_without_rehash_side_effect(): void
    {
        if (! defined('PASSWORD_ARGON2ID')) {
            $this->markTestSkipped('Argon2id is not available in this PHP build.');
        }

        config()->set('auth.legacy_plaintext_enabled', true);
        config()->set('auth.legacy_plaintext_allow_non_local', true);

        $user = User::factory()->create([
            'email' => 'legacy-hash@example.com',
            'password' => 'temporary-plain-password',
            'remember_token' => null,
            'is_active' => true,
        ]);

        $argonHash = password_hash('legacy-hash-secret', PASSWORD_ARGON2ID);
        DB::table('users')
            ->where('id', $user->id)
            ->update(['password' => $argonHash]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'legacy-hash@example.com',
            'password' => 'legacy-hash-secret',
            'remember' => true,
        ]);

        $response->assertOk();
        $this->assertAuthenticated();

        $user->refresh();
        $this->assertSame($argonHash, $user->password);
        $this->assertNull($user->remember_token);
        $this->assertNull($user->last_login_at);
    }

    public function test_login_does_not_persist_remember_token_by_default(): void
    {
        $user = User::factory()->create([
            'email' => 'remember-default@example.com',
            'password' => 'password',
            'remember_token' => null,
            'is_active' => true,
        ]);

        $this->postJson('/api/auth/login', [
            'email' => 'remember-default@example.com',
            'password' => 'password',
        ])->assertOk();

        $user->refresh();
        $this->assertNull($user->remember_token);
    }

    public function test_login_does_not_persist_remember_token_when_requested(): void
    {
        $user = User::factory()->create([
            'email' => 'remember-enabled@example.com',
            'password' => 'password',
            'remember_token' => null,
            'is_active' => true,
        ]);

        $this->postJson('/api/auth/login', [
            'email' => 'remember-enabled@example.com',
            'password' => 'password',
            'remember' => true,
        ])->assertOk();

        $user->refresh();
        $this->assertNull($user->remember_token);
    }

    public function test_login_has_no_default_user_seed_side_effect_even_in_local_environment(): void
    {
        $this->app->detectEnvironment(fn () => 'local');
        $this->withoutMiddleware();

        $this->assertTrue(app()->environment('local'));
        $this->assertSame(0, User::query()->count());

        $this->postJson('/api/auth/login', [
            'email' => 'missing-user@example.com',
            'password' => 'missing-password',
        ])->assertStatus(422)
            ->assertJsonPath('message', 'Používateľ s týmto e-mailom neexistuje.');

        $this->assertSame(0, User::query()->count());
        $this->assertDatabaseMissing('users', ['username' => 'astrokomunita']);
        $this->assertDatabaseMissing('users', ['username' => 'kozmobot']);
        $this->assertDatabaseMissing('users', ['username' => 'stellarbot']);
    }

    public function test_login_returns_generic_message_for_existing_email_with_wrong_password(): void
    {
        User::factory()->create([
            'email' => 'existing@example.com',
            'password' => 'correct-password',
            'is_active' => true,
        ]);

        $this->postJson('/api/auth/login', [
            'email' => 'existing@example.com',
            'password' => 'wrong-password',
        ])->assertStatus(422)
            ->assertJsonPath('message', 'Nesprávny e-mail alebo heslo.');
    }
}
