<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BotAvatarDefaultsTest extends TestCase
{
    use RefreshDatabase;

    public function test_stellarbot_gets_default_avatar_path_and_url_when_missing(): void
    {
        $bot = User::factory()->bot()->create([
            'username' => 'stellarbot',
            'avatar_path' => null,
            'avatar_mode' => 'generated',
        ])->fresh();

        $this->assertSame('bots/stellarbot/sb_blue.png', (string) $bot->avatar_path);
        $this->assertSame('image', (string) $bot->avatar_mode);
        $this->assertNotNull($bot->avatar_url);
        $this->assertStringEndsWith('/api/bot-avatars/stellarbot/sb_blue.png', (string) $bot->avatar_url);
    }

    public function test_non_bot_keeps_regular_avatar_path_behavior(): void
    {
        $user = User::factory()->create([
            'is_bot' => false,
            'role' => User::ROLE_USER,
            'avatar_path' => null,
        ])->fresh();

        $this->assertNull($user->avatar_path);
        $this->assertNull($user->avatar_url);
    }
}
