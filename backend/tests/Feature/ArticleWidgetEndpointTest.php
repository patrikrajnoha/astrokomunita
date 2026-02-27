<?php

namespace Tests\Feature;

use App\Models\BlogPost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ArticleWidgetEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_widget_endpoint_returns_sorted_three_plus_three_with_minimal_payload(): void
    {
        $author = User::factory()->create();

        $rows = [
            ['title' => 'A', 'slug' => 'a', 'views' => 10, 'created_at' => now()->subDays(5)],
            ['title' => 'B', 'slug' => 'b', 'views' => 90, 'created_at' => now()->subDays(4)],
            ['title' => 'C', 'slug' => 'c', 'views' => 50, 'created_at' => now()->subDays(3)],
            ['title' => 'D', 'slug' => 'd', 'views' => 80, 'created_at' => now()->subDays(2)],
            ['title' => 'E', 'slug' => 'e', 'views' => 30, 'created_at' => now()->subDay()],
            ['title' => 'F', 'slug' => 'f', 'views' => 70, 'created_at' => now()->subHours(12)],
        ];

        foreach ($rows as $row) {
            DB::table('blog_posts')->insert([
                'user_id' => $author->id,
                'title' => $row['title'],
                'slug' => $row['slug'],
                'content' => 'x',
                'views' => $row['views'],
                'published_at' => now()->subMinute(),
                'created_at' => $row['created_at'],
                'updated_at' => $row['created_at'],
            ]);
        }

        $response = $this->getJson('/api/articles/widget')
            ->assertOk()
            ->assertJsonStructure([
                'most_read',
                'latest',
                'generated_at',
            ]);

        $mostRead = $response->json('most_read');
        $latest = $response->json('latest');

        $this->assertCount(3, $mostRead);
        $this->assertCount(3, $latest);

        $this->assertSame(['b', 'd', 'f'], array_column($mostRead, 'slug'));
        $this->assertSame(['f', 'e', 'd'], array_column($latest, 'slug'));

        $this->assertSame(
            ['id', 'title', 'slug', 'thumbnail_url', 'views', 'created_at'],
            array_keys($mostRead[0])
        );
    }

    public function test_widget_endpoint_response_is_cached(): void
    {
        Cache::flush();
        $author = User::factory()->create();

        $post = BlogPost::query()->create([
            'user_id' => $author->id,
            'title' => 'Original',
            'slug' => 'original',
            'content' => 'x',
            'views' => 1,
            'published_at' => now()->subMinute(),
            'created_at' => now()->subMinute(),
            'updated_at' => now()->subMinute(),
        ]);

        $first = $this->getJson('/api/articles/widget')->assertOk();
        $generatedAt = $first->json('generated_at');

        DB::table('blog_posts')->where('id', $post->id)->update([
            'views' => 9999,
            'title' => 'Changed',
        ]);

        $second = $this->getJson('/api/articles/widget')->assertOk();

        $this->assertSame($generatedAt, $second->json('generated_at'));
        $this->assertSame('Original', $second->json('most_read.0.title'));
    }
}
