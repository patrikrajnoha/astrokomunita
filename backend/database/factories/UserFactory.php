<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'username' => fake()->unique()->regexify('[a-z][a-z0-9_]{7}'),
            'date_of_birth' => fake()->dateTimeBetween('-60 years', '-13 years')->format('Y-m-d'),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'role' => User::ROLE_USER,
            'is_admin' => false,
            'is_bot' => false,
            'is_banned' => false,
            'is_active' => true,
            'warning_count' => 0,
            'requires_email_verification' => false,
            'newsletter_subscribed' => false,
            'avatar_mode' => 'image',
            'avatar_color' => null,
            'avatar_icon' => null,
            'avatar_seed' => null,
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
            'requires_email_verification' => true,
        ]);
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => User::ROLE_ADMIN,
            'is_admin' => true,
        ]);
    }

    public function editor(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => User::ROLE_EDITOR,
            'is_admin' => false,
        ]);
    }

    public function bot(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => User::ROLE_BOT,
            'is_bot' => true,
            'email' => null,
            'email_verified_at' => null,
            'requires_email_verification' => false,
        ]);
    }
}
