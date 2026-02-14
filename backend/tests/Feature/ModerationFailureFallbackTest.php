<?php

namespace Tests\Feature;

use App\Jobs\ModeratePostJob;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ModerationFailureFallbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_microservice_failure_keeps_post_pending_and_attachment_blurred(): void
    {
        config()->set('moderation.enabled', true);
        config()->set('moderation.internal_token', 'test-token');

        Storage::fake('public');

        $user = User::factory()->create();
        $file = UploadedFile::fake()->create('sensitive.jpg', 256, 'image/jpeg');
        $path = $file->store('posts', 'public');

        $post = Post::factory()->create([
            'user_id' => $user->id,
            'content' => 'Potentially sensitive image',
            'attachment_path' => $path,
            'attachment_mime' => 'image/jpeg',
            'attachment_original_name' => 'sensitive.jpg',
            'attachment_size' => 12000,
            'moderation_status' => 'pending',
            'attachment_moderation_status' => 'pending',
            'attachment_is_blurred' => true,
        ]);

        Http::fake([
            '*' => Http::response([
                'error' => [
                    'code' => 'service_unavailable',
                    'message' => 'Down',
                ],
            ], 503),
        ]);

        $job = new ModeratePostJob((int) $post->id);

        $job->handle(app(\App\Services\Moderation\ModerationService::class));

        $post->refresh();

        $this->assertSame('pending', $post->moderation_status);
        $this->assertSame('pending', $post->attachment_moderation_status);
        $this->assertTrue((bool) $post->attachment_is_blurred);
    }
}
