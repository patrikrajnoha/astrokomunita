<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class AstroBotPurgeOldPostsTest extends TestCase
{
    use RefreshDatabase;

    public function test_purge_command_deletes_only_old_astrobot_posts(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-02 22:59:00'));

        $astrobot = User::factory()->create([
            'name' => 'AstroBot',
            'username' => 'astrobot',
            'is_bot' => true,
        ]);

        $human = User::factory()->create([
            'username' => 'human',
            'is_bot' => false,
        ]);

        $oldBotPost = Post::factory()->create([
            'user_id' => $astrobot->id,
            'created_at' => now()->subHours(25),
            'updated_at' => now()->subHours(25),
        ]);

        $recentBotPost = Post::factory()->create([
            'user_id' => $astrobot->id,
            'created_at' => now()->subHours(2),
            'updated_at' => now()->subHours(2),
        ]);

        $oldHumanPost = Post::factory()->create([
            'user_id' => $human->id,
            'created_at' => now()->subHours(30),
            'updated_at' => now()->subHours(30),
        ]);

        Artisan::call('astrobot:purge-old-posts --hours=24');

        $this->assertDatabaseMissing('posts', ['id' => $oldBotPost->id]);
        $this->assertDatabaseHas('posts', ['id' => $recentBotPost->id]);
        $this->assertDatabaseHas('posts', ['id' => $oldHumanPost->id]);
    }
}
