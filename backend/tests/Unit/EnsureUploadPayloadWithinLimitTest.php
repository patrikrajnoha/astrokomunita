<?php

namespace Tests\Unit;

use App\Http\Middleware\EnsureUploadPayloadWithinLimit;
use Illuminate\Http\Request;
use Tests\TestCase;

class EnsureUploadPayloadWithinLimitTest extends TestCase
{
    public function test_it_returns_json_413_when_content_length_exceeds_php_post_limit(): void
    {
        $middleware = new EnsureUploadPayloadWithinLimit();
        $request = Request::create('/api/posts', 'POST', [], [], [], [
            'CONTENT_LENGTH' => (string) (60 * 1024 * 1024),
            'CONTENT_TYPE' => 'multipart/form-data; boundary=----astrokomunita',
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $response = $middleware->handle($request, fn () => response()->json(['ok' => true]));

        $this->assertSame(413, $response->getStatusCode());
        $this->assertStringContainsString('Upload je prilis velky.', (string) $response->getContent());
    }

    public function test_it_allows_requests_that_fit_inside_php_post_limit(): void
    {
        $middleware = new EnsureUploadPayloadWithinLimit();
        $request = Request::create('/api/posts', 'POST', [], [], [], [
            'CONTENT_LENGTH' => '1024',
            'CONTENT_TYPE' => 'multipart/form-data; boundary=----astrokomunita',
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $response = $middleware->handle($request, fn () => response()->json(['ok' => true]));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('{"ok":true}', $response->getContent());
    }
}
