<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\User;
use App\Services\Auth\EmailVerificationSettingService;
use App\Services\Security\TurnstileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Mockery\MockInterface;
use Tests\TestCase;

class AuthRegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.turnstile.enabled', false);
        $this->withoutMiddleware();
    }

    public function test_registration_succeeds_with_valid_username_and_date_of_birth(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Tester',
            'email' => 'tester@example.com',
            'username' => 'My_Test_123',
            'date_of_birth' => now()->subYears(13)->toDateString(),
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('username', 'my_test_123');

        $this->assertDatabaseHas('users', [
            'email' => 'tester@example.com',
            'username' => 'my_test_123',
        ]);

        $user = User::query()->where('email', 'tester@example.com')->firstOrFail();
        $this->assertSame(
            now()->subYears(13)->toDateString(),
            Carbon::parse($user->date_of_birth)->toDateString()
        );
    }

    public function test_registration_fails_for_taken_username(): void
    {
        User::factory()->create([
            'username' => 'taken_name',
        ]);

        $response = $this->postJson('/api/auth/register', [
            'name' => 'Tester',
            'email' => 'new@example.com',
            'username' => 'Taken_Name',
            'date_of_birth' => now()->subYears(20)->toDateString(),
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['username']);
    }

    public function test_registration_fails_for_reserved_username(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Tester',
            'email' => 'reserved@example.com',
            'username' => 'admin',
            'date_of_birth' => now()->subYears(20)->toDateString(),
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['username']);
    }

    public function test_registration_fails_for_invalid_username_format(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Tester',
            'email' => 'invalid@example.com',
            'username' => '1badname',
            'date_of_birth' => now()->subYears(20)->toDateString(),
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['username']);
    }

    public function test_registration_fails_for_too_short_username(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Tester',
            'email' => 'short@example.com',
            'username' => 'ab',
            'date_of_birth' => now()->subYears(20)->toDateString(),
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['username']);
    }

    public function test_registration_fails_for_too_long_username(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Tester',
            'email' => 'long@example.com',
            'username' => 'averyveryveryverylongusername',
            'date_of_birth' => now()->subYears(20)->toDateString(),
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['username']);
    }

    public function test_registration_fails_when_user_is_younger_than_13(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Too Young',
            'email' => 'young@example.com',
            'username' => 'young_user',
            'date_of_birth' => now()->subYears(13)->addDay()->toDateString(),
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['date_of_birth']);
    }

    public function test_username_availability_endpoint_reports_available_username(): void
    {
        $response = $this->getJson('/api/auth/username-available?username=fresh_name');

        $response
            ->assertOk()
            ->assertJson([
                'username' => 'fresh_name',
                'normalized' => 'fresh_name',
                'available' => true,
                'reason' => 'ok',
            ]);
    }

    public function test_username_availability_endpoint_reports_taken_username(): void
    {
        User::factory()->create([
            'username' => 'taken_name',
        ]);

        $response = $this->getJson('/api/auth/username-available?username=Taken_Name');

        $response
            ->assertOk()
            ->assertJson([
                'normalized' => 'taken_name',
                'available' => false,
                'reason' => 'taken',
            ]);
    }

    public function test_username_availability_endpoint_reports_reserved_username(): void
    {
        $response = $this->getJson('/api/auth/username-available?username=admin');

        $response
            ->assertOk()
            ->assertJson([
                'normalized' => 'admin',
                'available' => false,
                'reason' => 'reserved',
            ]);
    }

    public function test_username_availability_endpoint_reports_invalid_username_format(): void
    {
        $response = $this->getJson('/api/auth/username-available?username=1bad');

        $response
            ->assertOk()
            ->assertJson([
                'normalized' => '1bad',
                'available' => false,
                'reason' => 'invalid',
            ]);
    }

    public function test_registration_persists_username_in_lowercase(): void
    {
        $this->postJson('/api/auth/register', [
            'name' => 'Tester',
            'email' => 'lowercase@example.com',
            'username' => 'Mixed_CASE_9',
            'date_of_birth' => now()->subYears(15)->toDateString(),
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertCreated();

        $this->assertDatabaseHas('users', [
            'email' => 'lowercase@example.com',
            'username' => 'mixed_case_9',
        ]);
    }

    public function test_registration_requires_email_verification_by_default(): void
    {
        Notification::fake();

        $this->postJson('/api/auth/register', [
            'name' => 'Tester',
            'email' => 'verify-default@example.com',
            'username' => 'verify_default',
            'date_of_birth' => now()->subYears(18)->toDateString(),
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertCreated();

        $user = User::query()->where('email', 'verify-default@example.com')->firstOrFail();

        $this->assertNull($user->email_verified_at);
        $this->assertTrue((bool) $user->requires_email_verification);
        Notification::assertNothingSent();
    }

    public function test_registration_disables_verification_requirement_when_setting_is_disabled(): void
    {
        Notification::fake();
        AppSetting::put(EmailVerificationSettingService::REQUIRE_EMAIL_VERIFICATION_FOR_NEW_USERS_KEY, '0');

        $this->postJson('/api/auth/register', [
            'name' => 'Tester',
            'email' => 'verify-disabled@example.com',
            'username' => 'verify_disabled',
            'date_of_birth' => now()->subYears(18)->toDateString(),
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertCreated();

        $user = User::query()->where('email', 'verify-disabled@example.com')->firstOrFail();

        $this->assertNull($user->email_verified_at);
        $this->assertFalse((bool) $user->requires_email_verification);
        Notification::assertNothingSent();
    }

    public function test_registration_returns_human_readable_min_length_error_for_password(): void
    {
        config()->set('app.locale', 'sk');
        config()->set('app.fallback_locale', 'sk');
        app()->setLocale('sk');

        $response = $this->postJson('/api/auth/register', [
            'name' => 'Tester',
            'email' => 'short-password@example.com',
            'username' => 'short_password',
            'date_of_birth' => now()->subYears(20)->toDateString(),
            'password' => '123',
            'password_confirmation' => '123',
        ]);

        $response->assertStatus(422);
        $firstPasswordError = (string) data_get($response->json(), 'errors.password.0', '');

        $this->assertStringNotContainsString('validation.min.string', $firstPasswordError);
        $this->assertStringContainsString('heslo', mb_strtolower($firstPasswordError));
    }

    public function test_registration_returns_422_when_turnstile_token_is_missing_and_enabled(): void
    {
        config()->set('services.turnstile.enabled', true);
        config()->set('services.turnstile.secret_key', 'test-secret');

        $response = $this->postJson('/api/auth/register', [
            'name' => 'Tester',
            'email' => 'missing-turnstile@example.com',
            'username' => 'missing_turnstile',
            'date_of_birth' => now()->subYears(20)->toDateString(),
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['turnstile_token']);
    }

    public function test_registration_returns_503_when_turnstile_is_enabled_but_secret_is_missing(): void
    {
        config()->set('services.turnstile.enabled', true);
        config()->set('services.turnstile.secret_key', '');

        $response = $this->postJson('/api/auth/register', [
            'name' => 'Tester',
            'email' => 'turnstile-secret-missing@example.com',
            'username' => 'turnstile_secret_missing',
            'date_of_birth' => now()->subYears(20)->toDateString(),
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response
            ->assertStatus(503)
            ->assertJson([
                'message' => 'Bezpečnostné overenie je dočasne nedostupné.',
            ]);
    }

    public function test_registration_returns_422_when_turnstile_verification_fails(): void
    {
        config()->set('services.turnstile.enabled', true);

        $this->mock(TurnstileService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('isEnabled')
                ->atLeast()
                ->once()
                ->andReturn(true);
            $mock->shouldReceive('hasSecretKey')
                ->atLeast()
                ->once()
                ->andReturn(true);
            $mock->shouldReceive('verify')
                ->once()
                ->with('invalid-token', '127.0.0.1')
                ->andReturn(false);
        });

        $response = $this->postJson('/api/auth/register', [
            'name' => 'Tester',
            'email' => 'failed-turnstile@example.com',
            'username' => 'failed_turnstile',
            'date_of_birth' => now()->subYears(20)->toDateString(),
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'turnstile_token' => 'invalid-token',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['turnstile_token']);
    }

    public function test_registration_succeeds_when_turnstile_is_disabled(): void
    {
        config()->set('services.turnstile.enabled', false);

        $response = $this->postJson('/api/auth/register', [
            'name' => 'Tester',
            'email' => 'turnstile-disabled@example.com',
            'username' => 'turnstile_disabled',
            'date_of_birth' => now()->subYears(20)->toDateString(),
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response
            ->assertCreated();

        $this->assertDatabaseHas('users', [
            'email' => 'turnstile-disabled@example.com',
            'username' => 'turnstile_disabled',
        ]);
    }
}
