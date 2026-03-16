<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RepairPostImageVariantsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_rebuilds_image_variant_from_stored_original(): void
    {
        config()->set('media.disk', 'public');
        config()->set('media.private_disk', 'local');
        config()->set('media.post_image_allowed_mimes', ['image/jpeg', 'image/png', 'image/webp', 'image/gif']);

        Storage::fake('public');
        Storage::fake('local');

        $user = User::factory()->create();
        $post = Post::factory()->for($user)->create([
            'content' => 'Repair image variant',
        ]);

        $originalPath = sprintf('posts/%d/images/%d/original.png', $post->id, $post->id);
        $corruptedWebPath = sprintf('posts/%d/images/%d/web.jpg', $post->id, $post->id);

        Storage::disk('local')->put($originalPath, $this->singlePixelPng());
        Storage::disk('public')->put($corruptedWebPath, 'corrupted-image-bytes');

        $post->forceFill([
            'attachment_path' => $corruptedWebPath,
            'attachment_web_path' => $corruptedWebPath,
            'attachment_original_path' => $originalPath,
            'attachment_mime' => 'image/jpeg',
            'attachment_web_mime' => 'image/jpeg',
            'attachment_original_mime' => 'image/png',
            'attachment_original_name' => 'pixel.png',
            'attachment_size' => strlen('corrupted-image-bytes'),
            'attachment_web_size' => strlen('corrupted-image-bytes'),
            'attachment_original_size' => strlen($this->singlePixelPng()),
            'attachment_web_width' => null,
            'attachment_web_height' => null,
            'attachment_variants_json' => null,
        ])->save();

        $this->artisan('posts:repair-image-variants', [
            'post_ids' => [$post->id],
        ])->assertExitCode(0);

        $post->refresh();

        $this->assertSame($originalPath, (string) $post->attachment_original_path);
        $this->assertStringStartsWith(
            sprintf('posts/%d/images/%d/web.', $post->id, $post->id),
            (string) $post->attachment_web_path
        );
        $this->assertSame($post->attachment_web_path, $post->attachment_path);
        $this->assertSame($post->attachment_web_mime, $post->attachment_mime);
        $this->assertGreaterThan(0, (int) $post->attachment_web_size);
        $this->assertSame((int) $post->attachment_web_size, (int) $post->attachment_size);
        $this->assertNotNull($post->attachment_variants_json);

        $webContents = Storage::disk('public')->get((string) $post->attachment_web_path);
        $this->assertNotSame('corrupted-image-bytes', $webContents);

        $imageInfo = @getimagesizefromstring($webContents);
        $this->assertIsArray($imageInfo);
        $this->assertSame(1, (int) $imageInfo[0]);
        $this->assertSame(1, (int) $imageInfo[1]);
        $this->assertSame(1, (int) $post->attachment_web_width);
        $this->assertSame(1, (int) $post->attachment_web_height);
    }

    private function singlePixelPng(): string
    {
        $decoded = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO7Z6XQAAAAASUVORK5CYII=',
            true
        );

        if ($decoded === false) {
            throw new \RuntimeException('Unable to decode PNG fixture.');
        }

        return $decoded;
    }
}
