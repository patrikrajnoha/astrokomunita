<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AuthRegistrationTest extends TestCase
{
    use RefreshDatabase;

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
}
