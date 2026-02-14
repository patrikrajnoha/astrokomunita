<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PollCreateValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_poll_with_one_option_fails(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->postJson('/api/posts', [
            'content' => 'Single option poll',
            'poll' => [
                'options' => ['Only one'],
            ],
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['poll.options']);
    }

    public function test_poll_with_five_options_fails(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->postJson('/api/posts', [
            'content' => 'Too many options',
            'poll' => [
                'options' => ['A', 'B', 'C', 'D', 'E'],
            ],
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['poll.options']);
    }

    public function test_poll_option_text_over_25_characters_fails(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->postJson('/api/posts', [
            'content' => 'Long option text',
            'poll' => [
                'options' => [str_repeat('x', 26), 'Valid option'],
            ],
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['poll.options.0']);
    }

    public function test_poll_duration_below_5_minutes_fails(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->postJson('/api/posts', [
            'content' => 'Too short duration',
            'poll' => [
                'options' => ['Yes', 'No'],
                'ends_in_seconds' => 299,
            ],
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['poll.ends_in_seconds']);
    }

    public function test_poll_duration_above_7_days_fails(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->postJson('/api/posts', [
            'content' => 'Too long duration',
            'poll' => [
                'options' => ['Yes', 'No'],
                'ends_in_seconds' => 604801,
            ],
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['poll.ends_in_seconds']);
    }
}
