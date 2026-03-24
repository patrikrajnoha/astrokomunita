<?php

namespace Tests\Feature;

use App\Models\BlogPost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminBlogPostIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_respects_per_page_parameter(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
            'role' => 'admin',
            'is_active' => true,
        ]);
        Sanctum::actingAs($admin);

        $author = User::factory()->create();
        for ($i = 1; $i <= 7; $i++) {
            BlogPost::query()->create([
                'user_id' => $author->id,
                'title' => 'Clanok ' . $i,
                'slug' => 'clanok-' . $i,
                'content' => 'Obsah clanku ' . $i,
                'published_at' => null,
            ]);
        }

        $this->getJson('/api/admin/blog-posts?per_page=5')
            ->assertOk()
            ->assertJsonPath('per_page', 5)
            ->assertJsonCount(5, 'data');
    }

    public function test_index_filters_by_search_query_for_title_and_author(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
            'role' => 'admin',
            'is_active' => true,
        ]);
        Sanctum::actingAs($admin);

        $anna = User::factory()->create([
            'name' => 'Anna Hvezda',
            'email' => 'anna@example.com',
        ]);
        $boris = User::factory()->create([
            'name' => 'Boris Nebo',
            'email' => 'boris@example.com',
        ]);

        $annaPost = BlogPost::query()->create([
            'user_id' => $anna->id,
            'title' => 'Sprievodca kometami',
            'slug' => 'sprievodca-kometami',
            'content' => 'Tipy pre nocne pozorovanie oblohy.',
            'published_at' => null,
        ]);

        BlogPost::query()->create([
            'user_id' => $boris->id,
            'title' => 'Planetarne minimum vybavenie',
            'slug' => 'planetarne-minimum-vybavenie',
            'content' => 'Zakladne kroky pre zaciatocnikov.',
            'published_at' => null,
        ]);

        $this->getJson('/api/admin/blog-posts?q=kometami')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.id', $annaPost->id);

        $this->getJson('/api/admin/blog-posts?q=Anna')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.id', $annaPost->id);
    }

    public function test_editor_can_upload_inline_blog_image_and_receive_public_url(): void
    {
        $editor = User::factory()->create([
            'is_admin' => false,
            'role' => 'editor',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        Sanctum::actingAs($editor);

        $disk = (string) config('media.disk', 'public');
        Storage::fake($disk);

        $response = $this->post('/api/admin/blog-posts/images', [
            'image' => UploadedFile::fake()->create('inline-image.png', 32, 'image/png'),
        ], [
            'Accept' => 'application/json',
        ]);

        $response
            ->assertCreated()
            ->assertJsonStructure([
                'path',
                'url',
                'mime',
                'name',
                'size',
            ]);

        $path = (string) $response->json('path', '');
        $url = (string) $response->json('url', '');

        $this->assertStringStartsWith('blog-inline/' . $editor->id . '/', $path);
        $this->assertStringContainsString('/api/media/file/blog-inline/' . $editor->id . '/', $url);
        Storage::disk($disk)->assertExists($path);
    }

    public function test_admin_can_hide_and_unhide_published_blog_post(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
            'role' => 'admin',
            'is_active' => true,
        ]);
        Sanctum::actingAs($admin);

        $author = User::factory()->create();
        $post = BlogPost::query()->create([
            'user_id' => $author->id,
            'title' => 'Skryvatelny clanok',
            'slug' => 'skryvatelny-clanok',
            'content' => 'Obsah skryvatelneho clanku.',
            'published_at' => now()->subMinute(),
            'is_hidden' => false,
        ]);

        BlogPost::query()->create([
            'user_id' => $author->id,
            'title' => 'Uz skryty clanok',
            'slug' => 'uz-skryty-clanok',
            'content' => 'Skryty obsah.',
            'published_at' => now()->subMinute(),
            'is_hidden' => true,
        ]);

        $this->getJson('/api/admin/blog-posts?status=hidden')
            ->assertOk()
            ->assertJsonPath('total', 1);

        $this->putJson('/api/admin/blog-posts/' . $post->id, [
            'is_hidden' => true,
        ])
            ->assertOk()
            ->assertJsonPath('id', $post->id)
            ->assertJsonPath('is_hidden', true);

        $this->assertDatabaseHas('blog_posts', [
            'id' => $post->id,
            'is_hidden' => true,
        ]);

        $publicHidden = $this->getJson('/api/blog-posts')
            ->assertOk();

        $hiddenPublicIds = collect($publicHidden->json('data'))
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();
        $this->assertNotContains($post->id, $hiddenPublicIds);

        $this->putJson('/api/admin/blog-posts/' . $post->id, [
            'is_hidden' => false,
        ])
            ->assertOk()
            ->assertJsonPath('id', $post->id)
            ->assertJsonPath('is_hidden', false);

        $this->assertDatabaseHas('blog_posts', [
            'id' => $post->id,
            'is_hidden' => false,
        ]);

        $publicVisible = $this->getJson('/api/blog-posts')
            ->assertOk();

        $visiblePublicIds = collect($publicVisible->json('data'))
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();
        $this->assertContains($post->id, $visiblePublicIds);
    }
}
