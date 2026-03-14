<?php

namespace Tests\Feature;

use App\Models\Observation;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MediaModerationAccessHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_media_and_download_routes_block_pending_and_flagged_attachments_for_non_admins(): void
    {
        config()->set('media.disk', 'public');
        Storage::fake('public');

        $owner = User::factory()->create();
        $admin = User::factory()->create(['is_admin' => true]);

        $file = UploadedFile::fake()->create('queued.jpg', 128, 'image/jpeg');
        $path = $file->store('posts', 'public');

        $post = Post::factory()->create([
            'user_id' => $owner->id,
            'attachment_path' => $path,
            'attachment_web_path' => $path,
            'attachment_mime' => 'image/jpeg',
            'attachment_original_mime' => 'image/jpeg',
            'attachment_original_name' => 'queued.jpg',
            'is_hidden' => false,
            'hidden_at' => null,
            'moderation_status' => 'ok',
            'attachment_moderation_status' => 'pending',
            'attachment_hidden_at' => null,
        ]);

        $this->get("/api/media/{$post->id}")
            ->assertForbidden();
        $this->get("/api/media/{$post->id}/download")
            ->assertForbidden();

        Sanctum::actingAs($owner);
        $this->get("/api/media/{$post->id}")
            ->assertForbidden();
        $this->get("/api/media/{$post->id}/download")
            ->assertForbidden();

        Sanctum::actingAs($admin);
        $this->get("/api/media/{$post->id}")
            ->assertOk();
        $this->get("/api/media/{$post->id}/download")
            ->assertOk();

        Sanctum::actingAs($owner);
        $post->forceFill([
            'attachment_moderation_status' => 'flagged',
            'attachment_hidden_at' => null,
        ])->save();

        $this->get("/api/media/{$post->id}")
            ->assertForbidden();
        $this->get("/api/media/{$post->id}/download")
            ->assertForbidden();

        $post->forceFill([
            'attachment_moderation_status' => 'ok',
            'attachment_hidden_at' => null,
        ])->save();

        $this->get("/api/media/{$post->id}")
            ->assertOk();
        $this->get("/api/media/{$post->id}/download")
            ->assertOk();
    }

    public function test_public_media_file_route_respects_post_moderation_and_observation_visibility(): void
    {
        config()->set('media.disk', 'public');
        Storage::fake('public');

        $owner = User::factory()->create();
        $other = User::factory()->create();
        $admin = User::factory()->create(['is_admin' => true]);

        $postFile = UploadedFile::fake()->create('pending.jpg', 128, 'image/jpeg');
        $postPath = $postFile->store('posts', 'public');

        $post = Post::factory()->create([
            'user_id' => $owner->id,
            'attachment_path' => $postPath,
            'attachment_web_path' => $postPath,
            'attachment_mime' => 'image/jpeg',
            'attachment_original_mime' => 'image/jpeg',
            'attachment_original_name' => 'pending.jpg',
            'is_hidden' => false,
            'hidden_at' => null,
            'moderation_status' => 'ok',
            'attachment_moderation_status' => 'pending',
            'attachment_hidden_at' => null,
        ]);

        $this->get('/api/media/file/' . $postPath)
            ->assertForbidden();

        $post->forceFill([
            'attachment_moderation_status' => 'ok',
            'attachment_hidden_at' => null,
        ])->save();

        $this->get('/api/media/file/' . $postPath)
            ->assertOk();

        $observationFile = UploadedFile::fake()->create('private-observation.jpg', 128, 'image/jpeg');
        $observationPath = $observationFile->store('observations/1/images', 'public');

        $observation = Observation::factory()->for($owner)->create([
            'is_public' => false,
        ]);
        $observation->media()->create([
            'path' => $observationPath,
            'mime_type' => 'image/jpeg',
        ]);

        $this->get('/api/media/file/' . $observationPath)
            ->assertForbidden();

        Sanctum::actingAs($other);
        $this->get('/api/media/file/' . $observationPath)
            ->assertForbidden();

        Sanctum::actingAs($owner);
        $this->get('/api/media/file/' . $observationPath)
            ->assertOk();

        Sanctum::actingAs($admin);
        $this->get('/api/media/file/' . $observationPath)
            ->assertOk();
    }
}

