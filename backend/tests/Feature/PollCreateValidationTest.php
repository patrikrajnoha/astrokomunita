<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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
                'options' => [
                    ['text' => 'Only one'],
                ],
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
                'options' => [
                    ['text' => 'A'],
                    ['text' => 'B'],
                    ['text' => 'C'],
                    ['text' => 'D'],
                    ['text' => 'E'],
                ],
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
                'options' => [
                    ['text' => str_repeat('x', 26)],
                    ['text' => 'Valid option'],
                ],
            ],
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['poll.options.0.text']);
    }

    public function test_poll_duration_below_5_minutes_fails(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->postJson('/api/posts', [
            'content' => 'Too short duration',
            'poll' => [
                'options' => [
                    ['text' => 'Yes'],
                    ['text' => 'No'],
                ],
                'duration_seconds' => 299,
            ],
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['poll.duration_seconds']);
    }

    public function test_poll_duration_above_7_days_fails(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->postJson('/api/posts', [
            'content' => 'Too long duration',
            'poll' => [
                'options' => [
                    ['text' => 'Yes'],
                    ['text' => 'No'],
                ],
                'duration_seconds' => 604801,
            ],
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['poll.duration_seconds']);
    }

    public function test_poll_and_attachment_combination_is_rejected(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->post('/api/posts', [
            'content' => 'Poll with attachment',
            'attachment' => UploadedFile::fake()->create('file.txt', 1, 'text/plain'),
            'poll' => [
                'options' => [
                    ['text' => 'A'],
                    ['text' => 'B'],
                ],
            ],
        ], ['Accept' => 'application/json']);

        $response->assertStatus(422)->assertJsonValidationErrors(['attachment']);
    }

    public function test_poll_option_image_must_be_image_file(): void
    {
        config()->set('media.disk', 'public');
        Storage::fake('public');

        Sanctum::actingAs(User::factory()->create());

        $response = $this->post('/api/posts', [
            'content' => 'Poll with invalid option image',
            'poll' => [
                'options' => [
                    [
                        'text' => 'A',
                        'image' => UploadedFile::fake()->create('bad.pdf', 10, 'application/pdf'),
                    ],
                    ['text' => 'B'],
                ],
            ],
        ], ['Accept' => 'application/json']);

        $response->assertStatus(422)->assertJsonValidationErrors(['poll.options.0.image']);
    }
}

