<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BotsSyncCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_normalizes_core_bots_and_reassigns_astrobot_content_to_stellarbot_idempotently(): void
    {
        User::factory()->admin()->create();

        $kozmobot = User::factory()->create([
            'username' => 'kozmobot',
            'role' => User::ROLE_USER,
            'is_bot' => false,
            'email' => 'kozmobot-legacy@example.test',
            'requires_email_verification' => true,
            'email_verified_at' => now(),
        ]);

        $stellarbot = User::factory()->create([
            'username' => 'stellarbot',
            'role' => User::ROLE_USER,
            'is_bot' => false,
            'email' => 'stellarbot-legacy@example.test',
            'requires_email_verification' => true,
            'email_verified_at' => now(),
        ]);

        $astrobot = User::factory()->create([
            'username' => 'astrobot',
            'role' => User::ROLE_USER,
            'is_bot' => false,
            'email' => 'astrobot-legacy@example.test',
        ]);

        $post = Post::factory()->for($astrobot)->create([
            'content' => 'Legacy astrobot post',
        ]);

        $this->artisan('bots:sync')
            ->expectsOutputToContain('Normalized bots:')
            ->assertExitCode(0);

        $this->assertDatabaseHas('users', [
            'id' => $kozmobot->id,
            'role' => User::ROLE_BOT,
            'is_bot' => true,
            'email' => null,
            'requires_email_verification' => false,
            'email_verified_at' => null,
        ]);
        $this->assertDatabaseHas('users', [
            'id' => $stellarbot->id,
            'role' => User::ROLE_BOT,
            'is_bot' => true,
            'email' => null,
            'requires_email_verification' => false,
            'email_verified_at' => null,
        ]);
        $this->assertDatabaseMissing('users', [
            'id' => $astrobot->id,
        ]);
        $this->assertDatabaseHas('posts', [
            'id' => $post->id,
            'user_id' => $stellarbot->id,
        ]);

        // Second run must be safe/no-op.
        $this->artisan('bots:sync')
            ->assertExitCode(0);

        $this->assertDatabaseHas('posts', [
            'id' => $post->id,
            'user_id' => $stellarbot->id,
        ]);
    }

    public function test_it_falls_back_to_kozmobot_when_stellarbot_is_missing(): void
    {
        User::factory()->admin()->create();

        $kozmobot = User::factory()->create([
            'username' => 'kozmobot',
            'role' => User::ROLE_USER,
            'is_bot' => false,
            'email' => 'kozmobot-legacy@example.test',
        ]);

        $astrobot = User::factory()->create([
            'username' => 'astrobot',
            'role' => User::ROLE_USER,
            'is_bot' => false,
            'email' => 'astrobot-legacy@example.test',
        ]);

        $post = Post::factory()->for($astrobot)->create([
            'content' => 'Legacy astrobot fallback post',
        ]);

        $this->artisan('bots:sync')->assertExitCode(0);

        $this->assertDatabaseMissing('users', [
            'id' => $astrobot->id,
        ]);
        $this->assertDatabaseHas('posts', [
            'id' => $post->id,
            'user_id' => $kozmobot->id,
        ]);
    }
}
