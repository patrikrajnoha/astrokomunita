<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SeedDefaultUsersCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_creates_expected_default_users_without_astrobot(): void
    {
        $this->artisan('app:seed-default-users')
            ->expectsOutputToContain('Created:')
            ->expectsOutputToContain('Updated:')
            ->assertExitCode(0);

        $this->assertSame(4, User::query()->count());
        $this->assertNull(User::query()->where('username', 'astrobot')->first());

        $admin = User::query()->where('username', 'admin')->firstOrFail();
        $this->assertTrue((bool) $admin->is_admin);
        $this->assertSame(User::ROLE_ADMIN, $admin->role);
        $this->assertNotNull($admin->email);

        $kozmobot = User::query()->where('username', 'kozmobot')->firstOrFail();
        $this->assertTrue((bool) $kozmobot->is_bot);
        $this->assertSame(User::ROLE_BOT, $kozmobot->role);
        $this->assertNull($kozmobot->email);
        $this->assertSame('Kozmo', (string) $kozmobot->name);

        $stellarbot = User::query()->where('username', 'stellarbot')->firstOrFail();
        $this->assertTrue((bool) $stellarbot->is_bot);
        $this->assertSame(User::ROLE_BOT, $stellarbot->role);
        $this->assertNull($stellarbot->email);
        $this->assertSame('Stella', (string) $stellarbot->name);

        $patrik = User::query()->where('username', 'patrik')->firstOrFail();
        $this->assertFalse((bool) $patrik->is_bot);
        $this->assertSame(User::ROLE_USER, $patrik->role);
        $this->assertTrue(Hash::check('patrik', (string) $patrik->password));
    }

    public function test_command_is_idempotent_and_updates_existing_legacy_bot_usernames(): void
    {
        User::query()->create([
            'name' => 'Legacy AstroBot',
            'username' => 'astrobot',
            'email' => 'astrobot@astrokomunita.local',
            'password' => Hash::make('legacy-password'),
            'is_admin' => false,
            'is_bot' => true,
            'role' => User::ROLE_USER,
            'is_active' => true,
            'is_banned' => false,
        ]);

        $this->artisan('app:seed-default-users')
            ->expectsOutputToContain('Created:')
            ->expectsOutputToContain('Updated:')
            ->assertExitCode(0);

        $this->assertNull(User::query()->where('username', 'astrobot')->first());

        $this->artisan('app:seed-default-users')
            ->expectsOutputToContain('Created: none')
            ->expectsOutputToContain('Updated:')
            ->assertExitCode(0);
    }
}
