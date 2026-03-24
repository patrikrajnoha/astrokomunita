<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminUserMediaUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_upload_bot_avatar_set_asset_avatar_and_upload_cover(): void
    {
        config([
            'media.disk' => 'public',
            'media.private_disk' => 'local',
        ]);
        Storage::fake('public');
        Storage::fake('local');

        $admin = User::factory()->admin()->create();
        $bot = User::factory()->bot()->create([
            'username' => 'kozmobot',
            'avatar_path' => 'avatars/10/old-avatar.png',
            'cover_path' => 'covers/10/old-cover.png',
        ]);

        Storage::disk('public')->put('avatars/10/old-avatar.png', 'old-avatar');
        Storage::disk('public')->put('covers/10/old-cover.png', 'old-cover');

        Sanctum::actingAs($admin);

        $avatarUploadResponse = $this->patch("/api/admin/users/{$bot->id}/avatar", [
            'file' => $this->fakeImage('avatar.png'),
        ], ['Accept' => 'application/json']);

        $avatarUploadResponse->assertOk()
            ->assertJsonPath('id', $bot->id)
            ->assertJsonPath('avatar_mode', 'image');
        $uploadedAvatarPath = (string) $avatarUploadResponse->json('avatar_path');
        $this->assertStringStartsWith("avatars/{$bot->id}/", $uploadedAvatarPath);
        Storage::disk('public')->assertExists($uploadedAvatarPath);
        Storage::disk('public')->assertMissing('avatars/10/old-avatar.png');

        $avatarResponse = $this->patchJson("/api/admin/users/{$bot->id}/avatar/preferences", [
            'avatar_mode' => 'image',
            'avatar_path' => 'bots/kozmobot/kb_red.png',
        ]);

        $avatarResponse->assertOk()
            ->assertJsonPath('id', $bot->id)
            ->assertJsonPath('avatar_mode', 'image')
            ->assertJsonPath('avatar_path', 'bots/kozmobot/kb_red.png');

        $coverResponse = $this->patch("/api/admin/users/{$bot->id}/cover", [
            'file' => $this->fakeImage('cover.png'),
        ], ['Accept' => 'application/json']);

        $coverResponse->assertOk()
            ->assertJsonPath('id', $bot->id);

        $coverPath = (string) $coverResponse->json('cover_path');
        $this->assertStringStartsWith("covers/{$bot->id}/", $coverPath);
        Storage::disk('public')->assertExists($coverPath);
        Storage::disk('public')->assertMissing('covers/10/old-cover.png');
    }

    public function test_non_admin_cannot_upload_bot_media(): void
    {
        config([
            'media.disk' => 'public',
            'media.private_disk' => 'local',
        ]);
        Storage::fake('public');
        Storage::fake('local');

        $editor = User::factory()->editor()->create();
        $bot = User::factory()->bot()->create([
            'username' => 'kozmobot',
        ]);

        Sanctum::actingAs($editor);

        $this->patchJson("/api/admin/users/{$bot->id}/avatar/preferences", [
            'avatar_mode' => 'image',
            'avatar_path' => 'bots/kozmobot/kb_blue.png',
        ])->assertForbidden();
    }

    public function test_admin_upload_endpoint_rejects_non_bot_target(): void
    {
        config([
            'media.disk' => 'public',
            'media.private_disk' => 'local',
        ]);
        Storage::fake('public');
        Storage::fake('local');

        $admin = User::factory()->admin()->create();
        $regularUser = User::factory()->create();

        Sanctum::actingAs($admin);

        $this->post("/api/admin/users/{$regularUser->id}/cover", [
            'file' => $this->fakeImage('avatar.png'),
        ], ['Accept' => 'application/json'])
            ->assertForbidden();
    }

    private function fakeImage(string $name): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO8JvWQAAAAASUVORK5CYII='
        ));
    }
}
