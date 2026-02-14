<?php

namespace Tests\Feature;

use App\Models\Poll;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PollVoteTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_vote_once_and_second_vote_returns_conflict(): void
    {
        $user = User::factory()->create();

        $poll = $this->createPoll();
        Sanctum::actingAs($user);
        $optionId = $poll['options'][0]['id'];

        $first = $this->postJson("/api/polls/{$poll['id']}/vote", [
            'option_id' => $optionId,
        ]);

        $first->assertOk()
            ->assertJsonPath('my_vote_option_id', $optionId)
            ->assertJsonPath('total_votes', 1);

        $this->assertDatabaseHas('poll_votes', [
            'poll_id' => $poll['id'],
            'poll_option_id' => $optionId,
            'user_id' => $user->id,
        ]);

        $this->assertDatabaseHas('poll_options', [
            'id' => $optionId,
            'votes_count' => 1,
        ]);

        $second = $this->postJson("/api/polls/{$poll['id']}/vote", [
            'option_id' => $poll['options'][1]['id'],
        ]);

        $second->assertStatus(409);
    }

    public function test_vote_after_poll_end_returns_422(): void
    {
        $user = User::factory()->create();

        $pollData = $this->createPoll();
        Sanctum::actingAs($user);
        Poll::query()->whereKey($pollData['id'])->update(['ends_at' => now()->subMinute()]);

        $response = $this->postJson("/api/polls/{$pollData['id']}/vote", [
            'option_id' => $pollData['options'][0]['id'],
        ]);

        $response->assertStatus(422);
    }

    public function test_total_votes_and_percents_are_computed_correctly(): void
    {
        $pollData = $this->createPoll();
        $pollId = $pollData['id'];
        $optionA = $pollData['options'][0]['id'];
        $optionB = $pollData['options'][1]['id'];

        $userA = User::factory()->create();
        Sanctum::actingAs($userA);
        $this->postJson("/api/polls/{$pollId}/vote", ['option_id' => $optionA])->assertOk();

        $userB = User::factory()->create();
        Sanctum::actingAs($userB);
        $this->postJson("/api/polls/{$pollId}/vote", ['option_id' => $optionA])->assertOk();

        $userC = User::factory()->create();
        Sanctum::actingAs($userC);
        $response = $this->postJson("/api/polls/{$pollId}/vote", ['option_id' => $optionB]);

        $response->assertOk()->assertJsonPath('total_votes', 3);
        $response->assertJsonPath('options.0.votes_count', 2);
        $response->assertJsonPath('options.1.votes_count', 1);
        $response->assertJsonPath('options.0.percent', 67);
        $response->assertJsonPath('options.1.percent', 33);
    }

    public function test_zero_vote_poll_returns_zero_percent_for_all_options(): void
    {
        $pollData = $this->createPoll();

        $response = $this->getJson("/api/polls/{$pollData['id']}");

        $response->assertOk()
            ->assertJsonPath('total_votes', 0)
            ->assertJsonPath('options.0.percent', 0)
            ->assertJsonPath('options.1.percent', 0);
    }

    private function createPoll(): array
    {
        $author = User::factory()->create();
        Sanctum::actingAs($author);

        $response = $this->postJson('/api/posts', [
            'content' => 'Poll question',
            'poll' => [
                'duration_preset' => '1d',
                'options' => ['Option A', 'Option B'],
            ],
        ]);

        $response->assertCreated();

        return $response->json('poll');
    }
}
