<?php

namespace Tests\Feature;

use App\Jobs\ModeratePostJob;
use App\Models\ModerationLog;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ModerationPostFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_posting_creates_pending_post_and_dispatches_moderation_job(): void
    {
        config()->set('moderation.enabled', true);

        $user = User::factory()->create();
        Sanctum::actingAs($user);
        Queue::fake();

        $response = $this->postJson('/api/posts', [
            'content' => 'Post for moderation queue',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('moderation_status', 'pending');

        $postId = (int) $response->json('id');

        $this->assertDatabaseHas('posts', [
            'id' => $postId,
            'moderation_status' => 'pending',
        ]);

        Queue::assertPushed(ModeratePostJob::class, function (ModeratePostJob $job) use ($postId) {
            return $job->postId === $postId;
        });
    }

    public function test_bot_post_skips_moderation_queue_and_is_immediately_ok(): void
    {
        config()->set('moderation.enabled', true);

        $botUser = User::factory()->create([
            'is_bot' => true,
            'username' => 'kozmo',
            'email' => 'kozmo.bot@example.test',
        ]);
        Sanctum::actingAs($botUser);
        Queue::fake();

        $response = $this->postJson('/api/posts', [
            'content' => 'Bot post should skip moderation',
            'feed_key' => 'astro',
            'author_kind' => 'bot',
            'bot_identity' => 'kozmo',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('moderation_status', 'ok');

        $postId = (int) $response->json('id');

        $this->assertDatabaseHas('posts', [
            'id' => $postId,
            'moderation_status' => 'ok',
            'author_kind' => 'bot',
        ]);

        Queue::assertNotPushed(ModeratePostJob::class);
    }

    public function test_feed_excludes_blocked_and_hidden_posts(): void
    {
        $user = User::factory()->create();

        $visiblePost = Post::factory()->for($user)->create([
            'content' => 'Visible post',
            'moderation_status' => 'ok',
            'is_hidden' => false,
            'hidden_at' => null,
        ]);

        Post::factory()->for($user)->create([
            'content' => 'Blocked post',
            'moderation_status' => 'blocked',
            'is_hidden' => true,
            'hidden_at' => now(),
        ]);

        Post::factory()->for($user)->create([
            'content' => 'Hidden manually',
            'moderation_status' => 'ok',
            'is_hidden' => true,
            'hidden_at' => now(),
        ]);

        $response = $this->getJson('/api/feed?with=counts');

        $response->assertOk();
        $response->assertJsonPath('data.0.id', $visiblePost->id);
        $response->assertJsonCount(1, 'data');
    }

    public function test_image_post_is_moderated_and_unblurred_after_successful_job(): void
    {
        config()->set('app.url', 'http://localhost');
        config()->set('media.disk', 'public');
        config()->set('media.private_disk', 'local');
        config()->set('moderation.enabled', true);
        config()->set('moderation.internal_token', 'internal-token');

        Storage::fake('public');
        Storage::fake('local');

        $user = User::factory()->create();
        Sanctum::actingAs($user);
        Queue::fake();

        $baseUrl = rtrim((string) config('moderation.base_url'), '/');
        Http::fake([
            $baseUrl . '/moderate/text' => Http::response([
                'decision' => 'ok',
                'toxicity_score' => 0.02,
                'hate_score' => 0.01,
                'scores' => [
                    'toxicity_labels' => ['toxic' => 0.02],
                    'hate_labels' => ['hate' => 0.01],
                ],
                'labels' => [
                    'toxicity' => 'toxic',
                    'hate' => 'not-hate',
                    'rule_match' => 'none',
                ],
                'model_versions' => [
                    'text' => 'unitary/toxic-bert',
                    'hate' => 'cardiffnlp/twitter-roberta-base-hate-latest',
                ],
                'latency_ms' => 45,
            ], 200),
            $baseUrl . '/moderate/image' => Http::response([
                'decision' => 'ok',
                'nsfw_score' => 0.03,
                'scores' => [
                    'normal' => 0.97,
                    'nsfw' => 0.03,
                ],
                'labels' => [
                    'top_label' => 'normal',
                ],
                'model_versions' => [
                    'image' => 'Falconsai/nsfw_image_detection',
                ],
                'latency_ms' => 52,
            ], 200),
        ]);

        $response = $this->postJson('/api/posts', [
            'content' => 'Image moderation regression test',
            'attachment' => $this->jpegFixtureUpload('moderation.jpg'),
        ]);

        $response->assertCreated();
        $response->assertJsonPath('moderation_status', 'pending');
        $response->assertJsonPath('attachment_moderation_status', 'pending');
        $response->assertJsonPath('attachment_is_blurred', true);

        $postId = (int) $response->json('id');

        Queue::assertPushed(ModeratePostJob::class, function (ModeratePostJob $job) use ($postId) {
            return $job->postId === $postId;
        });

        $post = Post::query()->findOrFail($postId);
        $this->assertSame('pending', $post->moderation_status);
        $this->assertSame('pending', $post->attachment_moderation_status);
        $this->assertTrue((bool) $post->attachment_is_blurred);

        $job = new ModeratePostJob($postId);
        $job->handle(app(\App\Services\Moderation\ModerationService::class));

        $post->refresh();

        $this->assertSame('ok', $post->moderation_status);
        $this->assertSame('ok', $post->attachment_moderation_status);
        $this->assertFalse((bool) $post->attachment_is_blurred);
        $this->assertSame('ok', data_get($post->moderation_summary, 'combined_decision'));
        $this->assertSame('ok', data_get($post->attachment_moderation_summary, 'decision'));

        $this->assertSame(2, ModerationLog::query()->where('entity_id', $postId)->count());
        $this->assertDatabaseHas('moderation_logs', [
            'entity_type' => 'post',
            'entity_id' => $postId,
            'decision' => 'ok',
            'error_code' => null,
        ]);
        $this->assertDatabaseHas('moderation_logs', [
            'entity_type' => 'media',
            'entity_id' => $postId,
            'decision' => 'ok',
            'error_code' => null,
        ]);

        Http::assertSentCount(2);
        Http::assertSent(fn ($request) => $request->url() === $baseUrl . '/moderate/text'
            && $request->hasHeader('X-Internal-Token', 'internal-token'));
        Http::assertSent(fn ($request) => $request->url() === $baseUrl . '/moderate/image'
            && $request->hasHeader('X-Internal-Token', 'internal-token'));
    }

    private function jpegFixtureUpload(string $filename): UploadedFile
    {
        $fixturePath = base_path('tests/Fixtures/images/large-sample.jpg');
        $contents = file_get_contents($fixturePath);
        if ($contents === false) {
            throw new \RuntimeException('Missing image fixture: ' . $fixturePath);
        }

        return UploadedFile::fake()->createWithContent($filename, $contents);
    }
}
