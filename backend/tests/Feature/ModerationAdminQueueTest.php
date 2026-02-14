<?php

namespace Tests\Feature;

use App\Models\ModerationLog;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ModerationAdminQueueTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_approve_and_reject_moderation_items(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
            'role' => 'admin',
        ]);

        Sanctum::actingAs($admin);

        $postForApprove = Post::factory()->create([
            'moderation_status' => 'flagged',
            'is_hidden' => false,
        ]);

        ModerationLog::query()->create([
            'entity_type' => 'post',
            'entity_id' => $postForApprove->id,
            'decision' => 'flagged',
            'scores' => ['toxicity_score' => 0.81],
            'labels' => ['toxicity' => 'toxic'],
            'model_versions' => ['text' => 'unitary/toxic-bert'],
            'latency_ms' => 20,
        ]);

        $this->postJson("/api/admin/moderation/{$postForApprove->id}/action", [
            'action' => 'approve',
            'note' => 'Safe context',
        ])->assertOk();

        $this->assertDatabaseHas('posts', [
            'id' => $postForApprove->id,
            'moderation_status' => 'ok',
            'is_hidden' => false,
        ]);

        $this->assertDatabaseHas('moderation_logs', [
            'entity_type' => 'post',
            'entity_id' => $postForApprove->id,
            'admin_action' => 'approve',
            'reviewed_by_admin_id' => $admin->id,
        ]);

        $postForReject = Post::factory()->create([
            'moderation_status' => 'flagged',
            'is_hidden' => false,
        ]);

        ModerationLog::query()->create([
            'entity_type' => 'post',
            'entity_id' => $postForReject->id,
            'decision' => 'flagged',
            'scores' => ['toxicity_score' => 0.95],
            'labels' => ['toxicity' => 'threat'],
            'model_versions' => ['text' => 'unitary/toxic-bert'],
            'latency_ms' => 22,
        ]);

        $this->postJson("/api/admin/moderation/{$postForReject->id}/action", [
            'action' => 'reject',
            'note' => 'Confirmed harmful',
        ])->assertOk();

        $this->assertDatabaseHas('posts', [
            'id' => $postForReject->id,
            'moderation_status' => 'blocked',
            'is_hidden' => true,
        ]);
    }
}
