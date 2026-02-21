<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SeedDefaultUsersCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_updates_user_when_username_exists_with_legacy_email(): void
    {
        User::query()->create([
            'name' => 'Legacy AstroBot',
            'username' => 'astrobot',
            'email' => 'astrobot@astrokomunita.local',
            'password' => Hash::make('legacy-password'),
            'is_admin' => false,
            'is_bot' => false,
            'role' => 'user',
            'is_active' => false,
            'is_banned' => true,
            'email_verified_at' => null,
        ]);

        $this->artisan('app:seed-default-users')
            ->expectsOutputToContain('Created:')
            ->expectsOutputToContain('Updated:')
            ->assertExitCode(0);

        $this->assertSame(3, User::query()->count());

        $astrobot = User::query()->where('username', 'astrobot')->firstOrFail();
        $this->assertSame('astrobot@astrobot.sk', $astrobot->email);
        $this->assertTrue((bool) $astrobot->is_bot);
        $this->assertFalse((bool) $astrobot->is_banned);
        $this->assertTrue((bool) $astrobot->is_active);
        $this->assertNotNull($astrobot->email_verified_at);
        $this->assertTrue(Hash::check('astrobot', (string) $astrobot->password));
    }

    public function test_command_is_idempotent_and_corrects_existing_user_flags(): void
    {
        User::query()->create([
            'name' => 'Wrong Admin',
            'username' => 'wrong_admin',
            'email' => 'admin@admin.sk',
            'password' => Hash::make('wrong-password'),
            'is_admin' => false,
            'is_bot' => true,
            'role' => 'user',
            'is_active' => false,
            'is_banned' => true,
            'email_verified_at' => null,
        ]);

        $this->artisan('app:seed-default-users')
            ->expectsOutputToContain('Created:')
            ->expectsOutputToContain('Updated:')
            ->assertExitCode(0);

        $this->assertSame(3, User::query()->count());

        $admin = User::query()->where('email', 'admin@admin.sk')->firstOrFail();
        $this->assertSame('admin', $admin->username);
        $this->assertTrue((bool) $admin->is_admin);
        $this->assertFalse((bool) $admin->is_bot);
        $this->assertSame('admin', $admin->role);
        $this->assertTrue((bool) $admin->is_active);
        $this->assertFalse((bool) $admin->is_banned);
        $this->assertNotNull($admin->email_verified_at);
        $this->assertTrue(Hash::check('admin', (string) $admin->password));

        $astrobot = User::query()->where('email', 'astrobot@astrobot.sk')->firstOrFail();
        $this->assertSame('astrobot', $astrobot->username);
        $this->assertFalse((bool) $astrobot->is_admin);
        $this->assertTrue((bool) $astrobot->is_bot);
        $this->assertSame('user', $astrobot->role);
        $this->assertTrue((bool) $astrobot->is_active);
        $this->assertNotNull($astrobot->email_verified_at);
        $this->assertTrue(Hash::check('astrobot', (string) $astrobot->password));

        $patrik = User::query()->where('email', 'patrik@patrik.sk')->firstOrFail();
        $this->assertSame('patrik', $patrik->username);
        $this->assertFalse((bool) $patrik->is_admin);
        $this->assertFalse((bool) $patrik->is_bot);
        $this->assertSame('user', $patrik->role);
        $this->assertTrue((bool) $patrik->is_active);
        $this->assertNotNull($patrik->email_verified_at);
        $this->assertTrue(Hash::check('patrik', (string) $patrik->password));

        $this->artisan('app:seed-default-users')
            ->expectsOutputToContain('Created: none')
            ->expectsOutputToContain('Updated:')
            ->assertExitCode(0);

        $this->assertSame(3, User::query()->count());
    }
}
