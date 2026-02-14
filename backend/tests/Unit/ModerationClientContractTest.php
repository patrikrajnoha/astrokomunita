<?php

namespace Tests\Unit;

use App\Services\Moderation\ModerationClient;
use App\Services\Moderation\ModerationClientException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ModerationClientContractTest extends TestCase
{
    public function test_moderate_text_sends_expected_payload_and_headers(): void
    {
        config()->set('moderation.base_url', 'http://moderation.local');
        config()->set('moderation.internal_token', 'internal-token');

        Http::fake([
            'http://moderation.local/moderate/text' => Http::response([
                'decision' => 'ok',
                'toxicity_score' => 0.1,
                'hate_score' => 0.05,
            ], 200),
        ]);

        $client = app(ModerationClient::class);
        $response = $client->moderateText('hello world');

        $this->assertSame('ok', $response['decision']);

        Http::assertSent(function ($request) {
            return $request->url() === 'http://moderation.local/moderate/text'
                && $request->hasHeader('X-Internal-Token', 'internal-token')
                && ($request['text'] ?? null) === 'hello world';
        });
    }

    public function test_moderate_image_throws_on_http_error(): void
    {
        config()->set('moderation.base_url', 'http://moderation.local');
        config()->set('moderation.internal_token', 'internal-token');

        Storage::fake('public');
        $image = UploadedFile::fake()->create('sample.jpg', 128, 'image/jpeg');
        $path = $image->store('images', 'public');
        $absolutePath = Storage::disk('public')->path($path);

        Http::fake([
            'http://moderation.local/moderate/image' => Http::response([
                'error' => [
                    'code' => 'upstream_error',
                    'message' => 'failure',
                ],
            ], 500),
        ]);

        $client = app(ModerationClient::class);

        $this->expectException(ModerationClientException::class);
        $client->moderateImageFromPath($absolutePath);
    }
}
