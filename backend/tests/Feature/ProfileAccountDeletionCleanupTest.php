<?php

namespace Tests\Feature;

use App\Models\Poll;
use App\Models\PollOption;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProfileAccountDeletionCleanupTest extends TestCase
{
    use RefreshDatabase;

    public function test_self_delete_cleans_user_media_files_from_storage(): void
    {
        config([
            'media.disk' => 'public',
            'media.private_disk' => 'local',
        ]);
        Storage::fake('public');
        Storage::fake('local');

        $user = User::factory()->create([
            'avatar_path' => 'avatars/10/avatar.png',
            'cover_path' => 'covers/10/cover.png',
        ]);

        Storage::disk('public')->put('avatars/10/avatar.png', 'avatar');
        Storage::disk('public')->put('covers/10/cover.png', 'cover');

        $post = Post::factory()->create([
            'user_id' => $user->id,
            'attachment_path' => 'posts/99/attachment.webp',
            'attachment_web_path' => 'posts/99/attachment.webp',
            'attachment_original_path' => 'posts/99/original.jpg',
            'moderation_status' => 'ok',
            'is_hidden' => false,
        ]);

        Storage::disk('public')->put('posts/99/attachment.webp', 'web');
        Storage::disk('local')->put('posts/99/original.jpg', 'original');

        $poll = Poll::query()->create([
            'post_id' => $post->id,
            'ends_at' => now()->addDay(),
        ]);

        PollOption::query()->create([
            'poll_id' => $poll->id,
            'text' => 'Option A',
            'position' => 1,
            'votes_count' => 0,
            'image_path' => 'polls/22/options/1.png',
        ]);

        Storage::disk('public')->put('polls/22/options/1.png', 'poll-image');

        Sanctum::actingAs($user);

        $this->deleteJson('/api/profile')
            ->assertOk()
            ->assertJsonPath('message', 'Ucet bol deaktivovany.');

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        Storage::disk('public')->assertMissing('avatars/10/avatar.png');
        Storage::disk('public')->assertMissing('covers/10/cover.png');
        Storage::disk('public')->assertMissing('posts/99/attachment.webp');
        Storage::disk('public')->assertMissing('polls/22/options/1.png');
        Storage::disk('local')->assertMissing('posts/99/original.jpg');
    }

    public function test_self_delete_is_idempotent_when_files_are_missing(): void
    {
        config([
            'media.disk' => 'public',
            'media.private_disk' => 'local',
        ]);
        Storage::fake('public');
        Storage::fake('local');

        $user = User::factory()->create([
            'avatar_path' => 'avatars/missing.png',
            'cover_path' => 'covers/missing.png',
        ]);

        Sanctum::actingAs($user);

        $this->deleteJson('/api/profile')
            ->assertOk()
            ->assertJsonPath('message', 'Ucet bol deaktivovany.');

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }
}
