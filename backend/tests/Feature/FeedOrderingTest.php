<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class FeedOrderingTest extends TestCase
{
    use RefreshDatabase;

    public function test_feed_places_pinned_posts_first_then_orders_by_created_at_desc(): void
    {
        $author = User::factory()->create();

        $pinnedOlder = $this->createFeedPost($author, [
            'content' => 'Pinned older',
            'created_at' => Carbon::parse('2026-01-01 12:00:00'),
            'pinned_at' => Carbon::parse('2026-01-02 00:00:00'),
        ]);
        $pinnedNewer = $this->createFeedPost($author, [
            'content' => 'Pinned newer',
            'created_at' => Carbon::parse('2026-01-01 12:05:00'),
            'pinned_at' => Carbon::parse('2026-01-02 00:01:00'),
        ]);
        $regularNewer = $this->createFeedPost($author, [
            'content' => 'Regular newer',
            'created_at' => Carbon::parse('2026-01-01 12:10:00'),
        ]);
        $regularOlder = $this->createFeedPost($author, [
            'content' => 'Regular older',
            'created_at' => Carbon::parse('2026-01-01 11:59:00'),
        ]);

        $response = $this->getJson('/api/feed?per_page=10');

        $response->assertOk();
        $response->assertJsonPath('data.0.id', $pinnedNewer->id);
        $response->assertJsonPath('data.1.id', $pinnedOlder->id);
        $response->assertJsonPath('data.2.id', $regularNewer->id);
        $response->assertJsonPath('data.3.id', $regularOlder->id);
    }

    public function test_feed_uses_id_desc_as_stable_tie_breaker_for_identical_timestamps(): void
    {
        $author = User::factory()->create();
        $timestamp = Carbon::parse('2026-01-01 12:00:00');

        $first = $this->createFeedPost($author, [
            'content' => 'First',
            'created_at' => $timestamp,
        ]);
        $second = $this->createFeedPost($author, [
            'content' => 'Second',
            'created_at' => $timestamp,
        ]);
        $third = $this->createFeedPost($author, [
            'content' => 'Third',
            'created_at' => $timestamp,
        ]);

        $response = $this->getJson('/api/feed?per_page=10');

        $response->assertOk();
        $response->assertJsonPath('data.0.id', $third->id);
        $response->assertJsonPath('data.1.id', $second->id);
        $response->assertJsonPath('data.2.id', $first->id);
    }

    public function test_feed_pagination_meta_is_present_and_consistent(): void
    {
        $author = User::factory()->create();

        for ($i = 0; $i < 5; $i++) {
            $this->createFeedPost($author, [
                'content' => 'Post '.$i,
                'created_at' => Carbon::parse('2026-01-01 12:00:00')->subMinute($i),
            ]);
        }

        $response = $this->getJson('/api/feed?per_page=2&page=2');

        $response->assertOk();
        $response->assertJsonPath('current_page', 2);
        $response->assertJsonPath('per_page', 2);
        $response->assertJsonPath('total', 5);
        $response->assertJsonPath('last_page', 3);
        $response->assertJsonCount(2, 'data');
    }

    public function test_feed_does_not_duplicate_posts_across_pages_when_pinned_posts_exist(): void
    {
        $author = User::factory()->create();

        $this->createFeedPost($author, [
            'content' => 'Pinned',
            'created_at' => Carbon::parse('2026-01-01 12:10:00'),
            'pinned_at' => Carbon::parse('2026-01-02 00:00:00'),
        ]);

        for ($i = 0; $i < 5; $i++) {
            $this->createFeedPost($author, [
                'content' => 'Regular '.$i,
                'created_at' => Carbon::parse('2026-01-01 12:00:00')->subMinute($i),
            ]);
        }

        $pageOne = $this->getJson('/api/feed?per_page=3&page=1')->assertOk()->json('data');
        $pageTwo = $this->getJson('/api/feed?per_page=3&page=2')->assertOk()->json('data');

        $pageOneIds = collect($pageOne)->pluck('id');
        $pageTwoIds = collect($pageTwo)->pluck('id');

        $this->assertCount(0, $pageOneIds->intersect($pageTwoIds));
    }

    private function createFeedPost(User $author, array $attributes = []): Post
    {
        return Post::factory()->for($author)->create(array_merge([
            'source_name' => null,
            'is_hidden' => false,
            'moderation_status' => 'ok',
            'hidden_at' => null,
            'pinned_at' => null,
        ], $attributes));
    }
}
