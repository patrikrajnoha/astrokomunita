<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StorageUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_uploading_avatar_stores_path_and_returns_absolute_url(): void
    {
        config()->set('media.disk', 'public');
        Storage::fake('public');

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/profile/media', [
            'type' => 'avatar',
            'file' => UploadedFile::fake()->createWithContent('avatar.png', base64_decode(
                'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO8JvWQAAAAASUVORK5CYII='
            )),
        ]);

        $response->assertOk();

        $path = (string) $response->json('avatar_path');
        $this->assertNotSame('', $path);
        $this->assertStringStartsWith("avatars/{$user->id}/", $path);
        Storage::disk('public')->assertExists($path);

        $avatarUrl = (string) $response->json('avatar_url');
        $this->assertStringStartsWith(rtrim((string) config('app.url'), '/'), $avatarUrl);
    }

    public function test_uploading_post_attachment_stores_path_and_returns_contract_fields(): void
    {
        config()->set('media.disk', 'public');
        Storage::fake('public');

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/posts', [
            'content' => 'Post with attachment',
            'attachment' => UploadedFile::fake()->create('attachment.txt', 1, 'text/plain'),
        ]);

        $response->assertCreated();
        $response->assertJsonStructure([
            'id',
            'content',
            'attachment_path',
            'attachment_url',
            'attachment_mime',
            'attachment_original_name',
            'attachment_size',
        ]);

        $postId = (int) $response->json('id');
        $path = (string) $response->json('attachment_path');
        $this->assertStringStartsWith("posts/{$postId}/", $path);
        Storage::disk('public')->assertExists($path);

        $this->assertSame($path, Post::query()->findOrFail($postId)->attachment_path);
        $attachmentUrl = (string) $response->json('attachment_url');
        $this->assertStringStartsWith(rtrim((string) config('app.url'), '/'), $attachmentUrl);
    }
}
