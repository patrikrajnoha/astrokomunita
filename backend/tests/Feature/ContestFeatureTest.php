<?php

namespace Tests\Feature;

use App\Models\Contest;
use App\Models\Notification;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ContestFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_contest(): void
    {
        Sanctum::actingAs($this->adminUser());

        $response = $this->postJson('/api/admin/contests', [
            'name' => 'Februarova sutaz',
            'description' => 'Sutaz o ceny.',
            'hashtag' => '#SuTaZiM',
            'starts_at' => now()->subDay()->toIso8601String(),
            'ends_at' => now()->addDay()->toIso8601String(),
            'status' => 'active',
        ]);

        $response->assertCreated()
            ->assertJsonPath('name', 'Februarova sutaz')
            ->assertJsonPath('hashtag', 'sutazim');

        $this->assertDatabaseHas('contests', [
            'name' => 'Februarova sutaz',
            'hashtag' => 'sutazim',
            'status' => 'active',
        ]);
    }

    public function test_active_contest_returns_via_public_endpoint(): void
    {
        $active = Contest::query()->create([
            'name' => 'Aktivna sutaz',
            'hashtag' => 'sutazim',
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHour(),
            'status' => 'active',
        ]);

        Contest::query()->create([
            'name' => 'Draft sutaz',
            'hashtag' => 'drafttag',
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHour(),
            'status' => 'draft',
        ]);

        $response = $this->getJson('/api/contests/active');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $active->id);
    }

    public function test_eligible_post_appears_in_participants(): void
    {
        $contest = Contest::query()->create([
            'name' => 'Hashtag sutaz',
            'hashtag' => 'sutazim',
            'starts_at' => now()->subDays(2),
            'ends_at' => now()->addDay(),
            'status' => 'active',
        ]);

        $eligibleUser = User::factory()->create(['username' => 'winner_user']);
        $eligiblePost = Post::factory()->create([
            'user_id' => $eligibleUser->id,
            'content' => 'Zapajam sa do #SuTaZiM!',
            'created_at' => now()->subHour(),
        ]);

        Post::factory()->create([
            'content' => 'Bez relevantneho hashtagu #ine',
            'created_at' => now()->subHour(),
        ]);
        Post::factory()->create([
            'content' => 'Mimo casoveho okna #sutazim',
            'created_at' => now()->subDays(5),
        ]);

        $response = $this->getJson("/api/contests/{$contest->id}/participants");

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.post_id', $eligiblePost->id)
            ->assertJsonPath('data.0.user_id', $eligibleUser->id)
            ->assertJsonPath('data.0.username', 'winner_user');
    }

    public function test_selecting_winner_sets_correct_winner_fields(): void
    {
        Sanctum::actingAs($this->adminUser());

        $contest = Contest::query()->create([
            'name' => 'Ukoncena sutaz',
            'hashtag' => 'sutazim',
            'starts_at' => now()->subDays(3),
            'ends_at' => now()->subHour(),
            'status' => 'active',
        ]);

        $post = Post::factory()->create([
            'content' => 'Moj prispevok #sutazim',
            'created_at' => now()->subDay(),
        ]);

        $response = $this->postJson("/api/admin/contests/{$contest->id}/select-winner", [
            'post_id' => $post->id,
        ]);

        $response->assertOk()
            ->assertJsonPath('winner_post_id', $post->id)
            ->assertJsonPath('winner_user_id', $post->user_id)
            ->assertJsonPath('status', 'finished');

        $this->assertDatabaseHas('contests', [
            'id' => $contest->id,
            'winner_post_id' => $post->id,
            'winner_user_id' => $post->user_id,
            'status' => 'finished',
        ]);
    }

    public function test_selecting_invalid_post_returns_422(): void
    {
        Sanctum::actingAs($this->adminUser());

        $contest = Contest::query()->create([
            'name' => 'Ukoncena sutaz',
            'hashtag' => 'sutazim',
            'starts_at' => now()->subDays(3),
            'ends_at' => now()->subHour(),
            'status' => 'active',
        ]);

        $invalidPost = Post::factory()->create([
            'content' => 'Toto nie je sutazny prispevok #ine',
            'created_at' => now()->subDay(),
        ]);

        $response = $this->postJson("/api/admin/contests/{$contest->id}/select-winner", [
            'post_id' => $invalidPost->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['post_id']);
    }

    public function test_winner_notification_created(): void
    {
        Sanctum::actingAs($this->adminUser());

        $contest = Contest::query()->create([
            'name' => 'Vyherna sutaz',
            'hashtag' => 'sutazim',
            'starts_at' => now()->subDays(2),
            'ends_at' => now()->subMinute(),
            'status' => 'active',
        ]);

        $post = Post::factory()->create([
            'content' => 'Ja idem vyhrat #sutazim',
            'created_at' => now()->subHour(),
        ]);

        $this->postJson("/api/admin/contests/{$contest->id}/select-winner", [
            'post_id' => $post->id,
        ])->assertOk();

        $notification = Notification::query()
            ->where('user_id', $post->user_id)
            ->where('type', 'contest_winner')
            ->latest('id')
            ->first();

        $this->assertNotNull($notification);
        $this->assertSame($contest->id, $notification->data['contest_id'] ?? null);
        $this->assertSame('Vyherna sutaz', $notification->data['contest_name'] ?? null);
        $this->assertSame($post->id, $notification->data['post_id'] ?? null);
    }

    private function adminUser(): User
    {
        return User::factory()->create([
            'role' => 'admin',
            'is_admin' => true,
            'email_verified_at' => now(),
            'is_active' => true,
        ]);
    }
}
