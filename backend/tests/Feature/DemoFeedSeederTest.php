<?php

namespace Tests\Feature;

use App\Enums\PostFeedKey;
use App\Models\Post;
use Database\Seeders\DefaultUsersSeeder;
use Database\Seeders\DemoFeedSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoFeedSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_feed_seeder_creates_idempotent_demo_posts(): void
    {
        app(DefaultUsersSeeder::class)->seed();

        $summaryFirst = app(DemoFeedSeeder::class)->seed();

        $this->assertNotEmpty($summaryFirst['created']);
        $this->assertSame(0, count($summaryFirst['skipped']));

        $demoPosts = Post::query()->where('source_name', 'demo_seed')->get();
        $this->assertSame(7, $demoPosts->count(), 'Expected 5 root posts + 2 replies.');

        $communityRoots = Post::query()
            ->where('source_name', 'demo_seed')
            ->where('feed_key', PostFeedKey::COMMUNITY->value)
            ->whereNull('parent_id')
            ->count();
        $this->assertSame(3, $communityRoots);

        $astroRoots = Post::query()
            ->where('source_name', 'demo_seed')
            ->where('feed_key', PostFeedKey::ASTRO->value)
            ->whereNull('parent_id')
            ->count();
        $this->assertSame(2, $astroRoots);

        $summarySecond = app(DemoFeedSeeder::class)->seed();
        $this->assertSame(0, count($summarySecond['created']));
        $this->assertGreaterThanOrEqual(7, count($summarySecond['updated']));
        $this->assertSame(7, Post::query()->where('source_name', 'demo_seed')->count());
    }
}

