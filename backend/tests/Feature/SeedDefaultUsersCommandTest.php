<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DefaultUsersSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use LogicException;
use Tests\TestCase;

class SeedDefaultUsersCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_creates_expected_default_users_without_astrobot(): void
    {
        $this->artisan('app:seed-default-users')
            ->expectsOutputToContain('Created:')
            ->expectsOutputToContain('Updated:')
            ->expectsOutputToContain('Deleted:')
            ->assertExitCode(0);

        $this->assertSame(3, User::query()->count());
        $this->assertNull(User::query()->where('username', 'astrobot')->first());

        $admin = User::query()->where('username', DefaultUsersSeeder::DEFAULT_ADMIN_USERNAME)->firstOrFail();
        $this->assertTrue((bool) $admin->is_admin);
        $this->assertSame(User::ROLE_ADMIN, $admin->role);
        $this->assertSame(DefaultUsersSeeder::DEFAULT_ADMIN_NAME, (string) $admin->name);
        $this->assertSame(DefaultUsersSeeder::DEFAULT_ADMIN_EMAIL, (string) $admin->email);
        $this->assertTrue(Hash::check(DefaultUsersSeeder::DEFAULT_ADMIN_PASSWORD, (string) $admin->password));

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
    }

    public function test_command_is_idempotent_and_removes_non_core_accounts(): void
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

        User::query()->create([
            'name' => 'Legacy User',
            'username' => 'legacy_user',
            'email' => 'legacy-user@example.test',
            'password' => Hash::make('legacy-password'),
            'is_admin' => false,
            'is_bot' => false,
            'role' => User::ROLE_USER,
            'is_active' => true,
            'is_banned' => false,
        ]);

        $this->artisan('app:seed-default-users')
            ->expectsOutputToContain('Created:')
            ->expectsOutputToContain('Updated:')
            ->expectsOutputToContain('Deleted:')
            ->assertExitCode(0);

        $this->assertNull(User::query()->where('username', 'astrobot')->first());
        $this->assertNull(User::query()->where('username', 'legacy_user')->first());
        $this->assertSame(3, User::query()->count());

        $this->artisan('app:seed-default-users')
            ->expectsOutputToContain('Created: none')
            ->expectsOutputToContain('Updated:')
            ->expectsOutputToContain('Deleted: none')
            ->assertExitCode(0);
    }

    public function test_seeder_can_skip_non_core_purge_when_requested(): void
    {
        User::query()->create([
            'name' => 'Legacy User',
            'username' => 'legacy_user',
            'email' => 'legacy-user@example.test',
            'password' => Hash::make('legacy-password'),
            'is_admin' => false,
            'is_bot' => false,
            'role' => User::ROLE_USER,
            'is_active' => true,
            'is_banned' => false,
        ]);

        $summary = app(DefaultUsersSeeder::class)->seed(false);

        $this->assertSame([], $summary['deleted']);
        $this->assertNotNull(User::query()->where('username', 'legacy_user')->first());
        $this->assertNotNull(User::query()->where('username', DefaultUsersSeeder::DEFAULT_ADMIN_USERNAME)->first());
    }

    public function test_seeder_refuses_direct_execution_in_production_without_explicit_opt_in(): void
    {
        $originalEnvironment = $this->app->environment();

        try {
            $this->app->detectEnvironment(fn () => 'production');

            $this->assertTrue(app()->environment('production'));

            $this->expectException(LogicException::class);
            $this->expectExceptionMessage('DefaultUsersSeeder refuses to run in production without explicit opt-in.');

            app(DefaultUsersSeeder::class)->seed(false);
        } finally {
            $this->app->detectEnvironment(fn () => $originalEnvironment);
        }
    }

    public function test_seeder_refuses_production_purge_even_with_explicit_opt_in(): void
    {
        $originalEnvironment = $this->app->environment();
        $originalAdminEmail = getenv('SEED_ADMIN_EMAIL');
        $originalAdminPassword = getenv('SEED_ADMIN_PASSWORD');

        try {
            putenv('SEED_ADMIN_EMAIL=admin@astrokomunita.sk');
            putenv('SEED_ADMIN_PASSWORD=very-strong-production-password');
            $this->app->detectEnvironment(fn () => 'production');

            $this->assertTrue(app()->environment('production'));

            $this->expectException(LogicException::class);
            $this->expectExceptionMessage('DefaultUsersSeeder refuses to purge non-core users in production.');

            app(DefaultUsersSeeder::class)->seed(true, true);
        } finally {
            if ($originalAdminEmail === false) {
                putenv('SEED_ADMIN_EMAIL=');
            } else {
                putenv('SEED_ADMIN_EMAIL='.$originalAdminEmail);
            }

            if ($originalAdminPassword === false) {
                putenv('SEED_ADMIN_PASSWORD=');
            } else {
                putenv('SEED_ADMIN_PASSWORD='.$originalAdminPassword);
            }

            $this->app->detectEnvironment(fn () => $originalEnvironment);
        }
    }

    public function test_seeder_refuses_production_seed_with_placeholder_admin_credentials(): void
    {
        $originalEnvironment = $this->app->environment();
        $originalAdminEmail = getenv('SEED_ADMIN_EMAIL');
        $originalAdminPassword = getenv('SEED_ADMIN_PASSWORD');

        try {
            putenv('SEED_ADMIN_EMAIL=');
            putenv('SEED_ADMIN_PASSWORD=');
            $this->app->detectEnvironment(fn () => 'production');

            $this->assertTrue(app()->environment('production'));

            $this->expectException(LogicException::class);
            $this->expectExceptionMessage('DefaultUsersSeeder refuses to run in production with placeholder admin email.');

            app(DefaultUsersSeeder::class)->seed(false, true);
        } finally {
            if ($originalAdminEmail === false) {
                putenv('SEED_ADMIN_EMAIL=');
            } else {
                putenv('SEED_ADMIN_EMAIL='.$originalAdminEmail);
            }

            if ($originalAdminPassword === false) {
                putenv('SEED_ADMIN_PASSWORD=');
            } else {
                putenv('SEED_ADMIN_PASSWORD='.$originalAdminPassword);
            }

            $this->app->detectEnvironment(fn () => $originalEnvironment);
        }
    }
}
