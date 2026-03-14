<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UploadImageModerationGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_avatar_upload_is_rejected_when_image_is_flagged(): void
    {
        $this->enableUploadModerationGuard();
        Storage::fake('public');

        $user = User::factory()->create([
            'avatar_mode' => 'generated',
        ]);
        Sanctum::actingAs($user);

        Http::fake([
            'http://moderation.test/moderate/image' => Http::response([
                'decision' => 'flagged',
                'nsfw_score' => 0.77,
                'scores' => ['nsfw' => 0.77],
            ], 200),
        ]);

        $response = $this->post('/api/profile/media', [
            'type' => 'avatar',
            'file' => $this->tinyPng('avatar.png'),
        ], ['Accept' => 'application/json']);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['file']);

        $this->assertNull(User::query()->findOrFail($user->id)->avatar_path);
        $this->assertCount(0, Storage::disk('public')->allFiles());
    }

    public function test_poll_option_image_is_rejected_when_image_is_flagged(): void
    {
        $this->enableUploadModerationGuard();
        Storage::fake('public');

        $admin = User::factory()->create([
            'is_admin' => true,
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);
        Sanctum::actingAs($admin);

        Http::fake([
            'http://moderation.test/moderate/image' => Http::response([
                'decision' => 'flagged',
                'nsfw_score' => 0.66,
                'scores' => ['nsfw' => 0.66],
            ], 200),
        ]);

        $response = $this->post('/api/posts', [
            'content' => 'Poll with moderated image',
            'poll' => [
                'duration_preset' => '1d',
                'options' => [
                    ['text' => 'A', 'image' => $this->tinyPng('option-a.png')],
                    ['text' => 'B'],
                ],
            ],
            'author_kind' => 'bot',
            'feed_key' => 'astro',
            'bot_identity' => 'kozmo',
        ], ['Accept' => 'application/json']);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['poll.options.0.image']);
    }

    public function test_observation_create_is_rejected_when_uploaded_image_is_flagged(): void
    {
        $this->enableUploadModerationGuard();
        Storage::fake('public');

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        Http::fake([
            'http://moderation.test/moderate/image' => Http::response([
                'decision' => 'blocked',
                'nsfw_score' => 0.98,
                'scores' => ['nsfw' => 0.98],
            ], 200),
        ]);

        $response = $this->post('/api/observations', [
            'title' => 'Observation title',
            'description' => 'Observation details',
            'observed_at' => now()->subHour()->toIso8601String(),
            'location_name' => 'Bratislava',
            'images' => [$this->tinyPng('observation.png')],
        ], ['Accept' => 'application/json']);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['images.0']);
        $this->assertDatabaseCount('observations', 0);
    }

    private function enableUploadModerationGuard(): void
    {
        config()->set('moderation.enabled', true);
        config()->set('moderation.enforce_upload_image_scan', true);
        config()->set('moderation.base_url', 'http://moderation.test');
        config()->set('moderation.internal_token', 'internal-token');
    }

    private function tinyPng(string $name): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            $name,
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO8JvWQAAAAASUVORK5CYII=')
        );
    }
}
