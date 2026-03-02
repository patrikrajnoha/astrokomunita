<?php

namespace Tests\Feature;

use App\Models\ModerationLog;
use App\Models\Post;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ModerationHubApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_overview_returns_service_status_and_counts(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
            'role' => 'admin',
        ]);
        Sanctum::actingAs($admin);

        $reporter = User::factory()->create();
        $secondReporter = User::factory()->create();
        $postOwner = User::factory()->create();

        $pendingPost = Post::factory()->for($postOwner)->create([
            'moderation_status' => 'pending',
            'created_at' => now()->subMinutes(10),
        ]);
        $flaggedPost = Post::factory()->for($postOwner)->create([
            'moderation_status' => 'flagged',
            'created_at' => now()->subMinutes(8),
        ]);
        $blockedPost = Post::factory()->for($postOwner)->create([
            'moderation_status' => 'blocked',
            'created_at' => now()->subMinutes(6),
        ]);
        $reviewedPost = Post::factory()->for($postOwner)->create([
            'moderation_status' => 'ok',
            'created_at' => now()->subMinutes(4),
        ]);

        ModerationLog::query()->create([
            'entity_type' => 'post',
            'entity_id' => $reviewedPost->id,
            'decision' => 'flagged',
            'scores' => ['toxicity_score' => 0.9],
            'labels' => ['toxicity' => 'toxic'],
            'model_versions' => ['text' => 'unit-test-model'],
            'latency_ms' => 18,
            'reviewed_by_admin_id' => $admin->id,
            'admin_action' => 'approve',
        ]);

        Report::query()->create([
            'reporter_user_id' => $reporter->id,
            'target_type' => 'post',
            'target_id' => $pendingPost->id,
            'reason' => 'spam',
            'message' => 'Open report',
            'status' => 'open',
        ]);
        Report::query()->create([
            'reporter_user_id' => $reporter->id,
            'target_type' => 'post',
            'target_id' => $flaggedPost->id,
            'reason' => 'abuse',
            'message' => 'Closed report',
            'status' => 'dismissed',
        ]);

        $baseUrl = rtrim((string) config('moderation.base_url'), '/');
        Http::fake([
            $baseUrl . '/health' => Http::response([
                'status' => 'ok',
            ], 200),
        ]);

        $response = $this->getJson('/api/admin/moderation/overview');

        $response->assertOk();
        $response->assertJsonPath('service.status', 'running');
        $response->assertJsonPath('counts.queue_pending', 1);
        $response->assertJsonPath('counts.queue_flagged', 1);
        $response->assertJsonPath('counts.queue_blocked', 1);
        $response->assertJsonPath('counts.queue_reviewed', 1);
        $response->assertJsonPath('counts.reports_open', 1);
        $response->assertJsonPath('counts.reports_closed', 1);
    }

    public function test_review_feed_returns_actionable_items_sorted_desc(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
            'role' => 'admin',
        ]);
        Sanctum::actingAs($admin);

        $reporter = User::factory()->create();
        $secondReporter = User::factory()->create();
        $owner = User::factory()->create(['name' => 'Autor']);

        $pendingPost = Post::factory()->for($owner)->create([
            'content' => 'Pending moderation item',
            'moderation_status' => 'pending',
            'created_at' => now()->subMinutes(12),
        ]);
        $flaggedPost = Post::factory()->for($owner)->create([
            'content' => 'Flagged moderation item',
            'moderation_status' => 'flagged',
            'moderation_summary' => [
                'text' => [
                    'toxicity_score' => 0.82,
                    'hate_score' => 0.15,
                ],
                'attachment' => [
                    'nsfw_score' => 0.01,
                ],
            ],
            'created_at' => now()->subMinutes(2),
        ]);
        Post::factory()->for($owner)->create([
            'content' => 'Reviewed item should not show',
            'moderation_status' => 'ok',
            'created_at' => now()->subMinute(),
        ]);

        Report::query()->create([
            'reporter_user_id' => $reporter->id,
            'target_type' => 'post',
            'target_id' => $pendingPost->id,
            'reason' => 'spam',
            'message' => 'Older open report',
            'status' => 'open',
            'created_at' => now()->subMinutes(20),
            'updated_at' => now()->subMinutes(20),
        ]);
        Report::query()->create([
            'reporter_user_id' => $reporter->id,
            'target_type' => 'post',
            'target_id' => $flaggedPost->id,
            'reason' => 'urazky',
            'message' => 'Newest open report',
            'status' => 'open',
            'created_at' => now()->subMinutes(1),
            'updated_at' => now()->subMinutes(1),
        ]);
        Report::query()->create([
            'reporter_user_id' => $secondReporter->id,
            'target_type' => 'post',
            'target_id' => $flaggedPost->id,
            'reason' => 'ignored',
            'message' => 'Closed report should not show',
            'status' => 'action_taken',
            'created_at' => now()->subSeconds(30),
            'updated_at' => now()->subSeconds(30),
        ]);

        $response = $this->getJson('/api/admin/moderation/review-feed?limit=10');
        $items = $response->json();

        $response->assertOk();
        $response->assertJsonCount(4);
        $this->assertSame('report', data_get($items, '0.kind'));
        $this->assertSame('open', data_get($items, '0.status'));
        $this->assertSame(
            ['queue', 'queue', 'report'],
            collect($items)->slice(1)->pluck('kind')->sort()->values()->all()
        );
        $this->assertSame(
            ['flagged', 'open', 'pending'],
            collect($items)->slice(1)->pluck('status')->sort()->values()->all()
        );
        $timestamps = collect($items)
            ->pluck('created_at')
            ->map(fn (?string $value) => strtotime((string) $value) ?: 0)
            ->values()
            ->all();
        $this->assertSame($timestamps, collect($timestamps)->sortDesc()->values()->all());
        $response->assertJsonMissing(['status' => 'action_taken']);
    }

    public function test_review_feed_supports_reviewed_mode(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
            'role' => 'admin',
        ]);
        Sanctum::actingAs($admin);

        $reporter = User::factory()->create();
        $owner = User::factory()->create();

        $reviewedPost = Post::factory()->for($owner)->create([
            'content' => 'Reviewed queue item',
            'moderation_status' => 'ok',
            'created_at' => now()->subMinutes(5),
        ]);

        ModerationLog::query()->create([
            'entity_type' => 'post',
            'entity_id' => $reviewedPost->id,
            'decision' => 'flagged',
            'scores' => ['toxicity_score' => 0.8],
            'labels' => ['toxicity' => 'toxic'],
            'model_versions' => ['text' => 'unit-test-model'],
            'latency_ms' => 11,
            'reviewed_by_admin_id' => $admin->id,
            'admin_action' => 'approve',
        ]);

        Report::query()->create([
            'reporter_user_id' => $reporter->id,
            'target_type' => 'post',
            'target_id' => $reviewedPost->id,
            'reason' => 'spam',
            'message' => 'Reviewed report',
            'status' => 'dismissed',
            'created_at' => now()->subMinutes(3),
            'updated_at' => now()->subMinutes(3),
        ]);

        $response = $this->getJson('/api/admin/moderation/review-feed?mode=reviewed&limit=10');

        $response->assertOk();
        $response->assertJsonCount(2);
        $response->assertJsonPath('0.kind', 'report');
        $response->assertJsonPath('0.status', 'dismissed');
        $response->assertJsonPath('1.kind', 'queue');
        $response->assertJsonPath('1.status', 'reviewed');
    }
}
