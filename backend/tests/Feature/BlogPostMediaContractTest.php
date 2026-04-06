<?php

namespace Tests\Feature;

use App\Models\BlogPost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BlogPostMediaContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_blog_post_exposes_cover_image_via_public_media_url_contract(): void
    {
        config()->set('app.url', 'http://astrokomunita.test');
        config()->set('media.disk', 'public');
        Storage::fake('public');

        $author = User::factory()->create();
        $coverPath = sprintf('blog-covers/%d/%s', $author->id, 'cover.png');
        Storage::disk('public')->put($coverPath, 'fake-image-bytes');

        $post = BlogPost::query()->create([
            'user_id' => $author->id,
            'title' => 'Media contract article',
            'slug' => 'media-contract-article',
            'content' => '<p>Obsah</p>',
            'published_at' => now()->subMinute(),
            'is_hidden' => false,
            'cover_image_path' => $coverPath,
            'cover_image_mime' => 'image/png',
            'cover_image_original_name' => 'cover.png',
            'cover_image_size' => 128,
        ]);

        $response = $this->getJson('/api/blog-posts/' . $post->slug)
            ->assertOk();

        $coverUrl = (string) $response->json('cover_image_url');
        $this->assertTrue(
            str_starts_with($coverUrl, '/api/media/file/')
            || str_starts_with($coverUrl, rtrim((string) config('app.url'), '/') . '/api/media/file/'),
            'Blog cover URL must point to the public media API endpoint.'
        );
    }
}
