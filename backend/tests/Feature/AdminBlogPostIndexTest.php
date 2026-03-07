<?php

namespace Tests\Feature;

use App\Models\BlogPost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}

