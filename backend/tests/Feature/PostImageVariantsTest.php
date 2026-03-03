<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PostImageVariantsTest extends TestCase
{
    use RefreshDatabase;

    public function test_post_creation_with_png_upload_succeeds_and_stores_file_metadata(): void
    {
        if (!function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('GD extension is required for UploadedFile::fake()->image().');
        }

        config()->set('app.url', 'http://localhost');
        config()->set('media.disk', 'public');
        config()->set('media.private_disk', 'local');
        Storage::fake('public');
        Storage::fake('local');

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->post('/api/posts', [
            'content' => 'PNG attachment',
            'attachment' => UploadedFile::fake()->image('picture.png', 320, 180),
        ], ['Accept' => 'application/json']);

        $response->assertCreated();
        $response->assertJsonPath('attachment_url', "/api/media/{$response->json('id')}");
        $this->assertNotEmpty((string) $response->json('attachment_path'));

        $post = Post::query()->findOrFail((int) $response->json('id'));
        $this->assertNotNull($post->attachment_path);
        $this->assertNotNull($post->attachment_original_path);
        $this->assertNotNull($post->attachment_web_path);
        $this->assertNotNull($post->attachment_mime);

        Storage::disk('local')->assertExists((string) $post->attachment_original_path);
        Storage::disk('public')->assertExists((string) $post->attachment_web_path);
    }

    public function test_post_creation_with_gif_keeps_gif_variant_without_conversion(): void
    {
        config()->set('app.url', 'http://localhost');
        config()->set('media.disk', 'public');
        config()->set('media.private_disk', 'local');
        Storage::fake('public');
        Storage::fake('local');

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->post('/api/posts', [
            'content' => 'GIF attachment',
            'attachment' => UploadedFile::fake()->create('anim.gif', 128, 'image/gif'),
        ], ['Accept' => 'application/json']);

        $response->assertCreated();
        $response->assertJsonPath('attachment_url', "/api/media/{$response->json('id')}");
        $response->assertJsonPath('attachment_mime', 'image/gif');

        $post = Post::query()->findOrFail((int) $response->json('id'));
        $this->assertSame('image/gif', $post->attachment_original_mime);
        $this->assertSame('image/gif', $post->attachment_web_mime);
        $this->assertStringEndsWith('.gif', (string) $post->attachment_original_path);
        $this->assertStringEndsWith('.gif', (string) $post->attachment_web_path);
        $this->assertFalse((bool) data_get($post->attachment_variants_json, 'processed', true));

        Storage::disk('local')->assertExists((string) $post->attachment_original_path);
        Storage::disk('public')->assertExists((string) $post->attachment_web_path);
    }

    public function test_invalid_uploaded_file_does_not_trigger_internal_server_error(): void
    {
        config()->set('app.url', 'http://localhost');
        config()->set('media.disk', 'public');
        config()->set('media.private_disk', 'local');
        Storage::fake('public');
        Storage::fake('local');

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $tmp = tmpfile();
        if ($tmp === false) {
            $this->fail('Unable to create temporary file.');
        }

        fwrite($tmp, 'x');
        $meta = stream_get_meta_data($tmp);
        $tmpPath = (string) ($meta['uri'] ?? '');

        $oversized = new UploadedFile(
            $tmpPath,
            'oversized.png',
            'image/png',
            UPLOAD_ERR_INI_SIZE,
            true
        );

        $response = $this->post('/api/posts', [
            'content' => 'Invalid upload',
            'attachment' => $oversized,
        ], ['Accept' => 'application/json']);

        fclose($tmp);

        $response->assertStatus(422)->assertJsonValidationErrors(['attachment']);
    }

    public function test_image_upload_creates_web_and_original_variants(): void
    {
        config()->set('app.url', 'http://localhost');
        config()->set('media.disk', 'public');
        config()->set('media.private_disk', 'local');
        Storage::fake('public');
        Storage::fake('local');

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/posts', [
            'content' => 'Image variants test',
            'attachment' => $this->jpegFixtureUpload('variants.jpg'),
        ]);

        $response->assertCreated();

        $post = Post::query()->findOrFail((int) $response->json('id'));
        $this->assertNotNull($post->attachment_original_path);
        $this->assertNotNull($post->attachment_web_path);
        $this->assertNotNull($post->attachment_original_size);
        $this->assertNotNull($post->attachment_web_size);

        Storage::disk('local')->assertExists((string) $post->attachment_original_path);
        Storage::disk('public')->assertExists((string) $post->attachment_web_path);

        $this->assertStringStartsWith(
            sprintf('posts/%d/images/%d/original.', $post->id, $post->id),
            (string) $post->attachment_original_path
        );
        $this->assertStringStartsWith(
            sprintf('posts/%d/images/%d/web.', $post->id, $post->id),
            (string) $post->attachment_web_path
        );

        $this->assertSame($post->attachment_web_path, $post->attachment_path);
        $this->assertSame($post->attachment_web_mime, $post->attachment_mime);
        $this->assertSame((int) $post->attachment_web_size, (int) $post->attachment_size);

        $processed = (bool) data_get($post->attachment_variants_json, 'processed', false);
        if ($processed) {
            $this->assertLessThan((int) $post->attachment_original_size, (int) $post->attachment_web_size);
        } else {
            $this->assertGreaterThan(0, (int) $post->attachment_web_size);
        }
    }

    public function test_download_endpoint_requires_access(): void
    {
        config()->set('app.url', 'http://localhost');
        config()->set('media.disk', 'public');
        config()->set('media.private_disk', 'local');
        Storage::fake('public');
        Storage::fake('local');

        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $post = Post::factory()->for($owner)->create([
            'content' => 'Private image post',
            'is_hidden' => true,
            'attachment_path' => 'posts/secure/legacy-web.jpg',
            'attachment_original_path' => 'posts/secure/original.jpg',
            'attachment_web_path' => 'posts/secure/web.jpg',
            'attachment_mime' => 'image/webp',
            'attachment_original_mime' => 'image/jpeg',
            'attachment_web_mime' => 'image/webp',
            'attachment_original_name' => 'secure.jpg',
            'attachment_size' => 1024,
            'attachment_original_size' => 4096,
            'attachment_web_size' => 1024,
            'attachment_web_width' => 1600,
            'attachment_web_height' => 1000,
        ]);

        Storage::disk('local')->put('posts/secure/original.jpg', 'image-data');
        Storage::disk('public')->put('posts/secure/web.jpg', 'image-data');

        $this->get("/api/media/{$post->id}/download")->assertForbidden();

        Sanctum::actingAs($stranger);
        $this->get("/api/media/{$post->id}/download")->assertForbidden();

        Sanctum::actingAs($owner);
        $download = $this->get("/api/media/{$post->id}/download");
        $download->assertOk();
        $this->assertStringContainsString(
            'attachment;',
            strtolower((string) $download->headers->get('content-disposition'))
        );
    }

    public function test_download_endpoint_returns_uncompressed_original_when_available(): void
    {
        config()->set('app.url', 'http://localhost');
        config()->set('media.disk', 'public');
        config()->set('media.private_disk', 'local');
        Storage::fake('public');
        Storage::fake('local');

        $owner = User::factory()->create();
        Sanctum::actingAs($owner);

        $post = Post::factory()->for($owner)->create([
            'content' => 'Original download test',
            'attachment_path' => 'posts/42/images/42/web.webp',
            'attachment_original_path' => 'posts/42/images/42/original.jpg',
            'attachment_web_path' => 'posts/42/images/42/web.webp',
            'attachment_mime' => 'image/webp',
            'attachment_original_mime' => 'image/jpeg',
            'attachment_web_mime' => 'image/webp',
            'attachment_original_name' => 'photo.jpg',
        ]);

        $originalBytes = 'ORIGINAL-JPEG-BYTES';
        $webBytes = 'WEBP-BYTES';
        Storage::disk('local')->put((string) $post->attachment_original_path, $originalBytes);
        Storage::disk('public')->put((string) $post->attachment_web_path, $webBytes);

        $download = $this->get("/api/media/{$post->id}/download");
        $download->assertOk();
        $download->assertHeader('content-type', 'image/jpeg');
        $this->assertStringContainsString(
            '-original.jpg',
            strtolower((string) $download->headers->get('content-disposition'))
        );

        ob_start();
        $download->sendContent();
        $content = (string) ob_get_clean();
        $this->assertSame($originalBytes, $content);
    }

    public function test_response_payload_contains_web_url_and_download_url(): void
    {
        config()->set('app.url', 'http://localhost');
        config()->set('media.disk', 'public');
        config()->set('media.private_disk', 'local');
        Storage::fake('public');
        Storage::fake('local');

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $create = $this->postJson('/api/posts', [
            'content' => 'Payload image post',
            'attachment' => $this->jpegFixtureUpload('payload.jpg'),
        ]);
        $create->assertCreated();

        $postId = (int) $create->json('id');
        $create->assertJsonPath('attachment_download_url', "/api/media/{$postId}/download");
        $create->assertJsonPath('attachment_url', "/api/media/{$postId}");

        $feed = $this->getJson('/api/feed?with=counts');
        $feed->assertOk();
        $feed->assertJsonPath('data.0.id', $postId);
        $feed->assertJsonPath('data.0.attachment_url', "/api/media/{$postId}");
        $feed->assertJsonPath('data.0.attachment_download_url', "/api/media/{$postId}/download");
        $this->assertNotNull($feed->json('data.0.attachment_width'));
        $this->assertNotNull($feed->json('data.0.attachment_height'));
        $this->assertNotNull($feed->json('data.0.attachment_size_web'));
    }

    private function jpegFixtureUpload(string $filename): UploadedFile
    {
        $fixturePath = base_path('tests/Fixtures/images/large-sample.jpg');
        $contents = file_get_contents($fixturePath);
        if ($contents === false) {
            throw new \RuntimeException('Missing image fixture: ' . $fixturePath);
        }

        return UploadedFile::fake()->createWithContent($filename, $contents);
    }
}
