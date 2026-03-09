<?php

namespace Tests\Feature;

use App\Models\Contest;
use App\Models\Hashtag;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ContestFeatureTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_admin_contest_mutation_endpoints_are_not_available(): void
    {
        Sanctum::actingAs($this->adminUser());

        $contest = Contest::query()->create([
            'name' => 'Legacy route contest',
            'hashtag' => 'sutazim',
            'starts_at' => now()->subDays(3),
            'ends_at' => now()->addDay(),
            'status' => 'active',
        ]);

        $this->postJson('/api/admin/contests', [
            'name' => 'Februarova sutaz',
            'starts_at' => now()->subDay()->toIso8601String(),
            'ends_at' => now()->addDay()->toIso8601String(),
        ])->assertNotFound();

        $this->patchJson("/api/admin/contests/{$contest->id}", [
            'name' => 'Updated',
        ])->assertNotFound();

        $this->postJson("/api/admin/contests/{$contest->id}/select-winner", [
            'post_id' => 1,
        ])->assertNotFound();
    }

    public function test_admin_contest_list_endpoint_is_not_available(): void
    {
        Sanctum::actingAs($this->adminUser());

        $response = $this->getJson('/api/admin/contests');

        $response->assertNotFound();
    }

    public function test_admin_can_preview_hashtags_with_posts_and_user_email(): void
    {
        Sanctum::actingAs($this->adminUser());

        $user = User::factory()->create([
            'username' => 'preview_user',
            'email' => 'preview@example.com',
        ]);

        $matchingPost = Post::factory()->create([
            'user_id' => $user->id,
            'content' => 'Sutazny post #sutazim',
            'created_at' => now()->subDay(),
        ]);

        $outOfRangePost = Post::factory()->create([
            'content' => 'Stary post #sutazim',
            'created_at' => now()->subDays(90),
        ]);

        $matchingTag = Hashtag::query()->create(['name' => 'sutazim']);
        $matchingTag->posts()->sync([$matchingPost->id, $outOfRangePost->id]);

        Hashtag::query()->create(['name' => 'inytag']);

        $response = $this->getJson('/api/admin/contests/hashtags-preview?' . http_build_query([
            'query' => 'sutaz',
            'from' => now()->subDays(10)->toDateString(),
            'to' => now()->toDateString(),
            'hashtags_limit' => 10,
            'posts_limit' => 5,
        ]));

        $response->assertOk()
            ->assertJsonPath('data.0.name', 'sutazim')
            ->assertJsonPath('data.0.posts_count', 1)
            ->assertJsonPath('data.0.posts.0.id', $matchingPost->id)
            ->assertJsonPath('data.0.posts.0.user.email', 'preview@example.com');
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
