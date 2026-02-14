<?php

namespace App\Services\Moderation;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class ModerationClient
{
    public function moderateText(string $text, ?string $lang = null): array
    {
        $this->logRequestDiagnostics('/moderate/text');

        return $this->postJson('/moderate/text', [
            'text' => $text,
            'lang' => $lang,
        ]);
    }

    public function moderateImageFromPath(string $absolutePath): array
    {
        $contents = @file_get_contents($absolutePath);
        if ($contents === false) {
            throw new ModerationClientException(
                'Unable to read attachment for moderation.',
                'file_read_error'
            );
        }

        $request = $this->baseRequest()->attach(
            'image',
            $contents,
            basename($absolutePath)
        );
        $this->logRequestDiagnostics('/moderate/image');

        try {
            $response = $request->post('/moderate/image');
        } catch (ConnectionException $exception) {
            $this->logFailureDiagnostics('/moderate/image', 0, 'connection_error');
            throw new ModerationClientException(
                'Unable to connect to moderation service.',
                'connection_error'
            );
        } catch (Throwable $exception) {
            $this->logFailureDiagnostics('/moderate/image', 0, 'service_error');
            throw new ModerationClientException(
                'Moderation image request failed.',
                'service_error'
            );
        }

        if ($response->failed()) {
            $this->logFailureDiagnostics('/moderate/image', $response->status(), (string) ($response->json('error.code') ?? 'service_error'));
            throw new ModerationClientException(
                (string) ($response->json('error.message') ?? 'Moderation image request failed.'),
                (string) ($response->json('error.code') ?? 'service_error'),
                $response->status()
            );
        }

        return $response->json() ?? [];
    }

    private function postJson(string $path, array $payload): array
    {
        try {
            $response = $this->baseRequest()->post($path, $payload);
        } catch (ConnectionException $exception) {
            $this->logFailureDiagnostics($path, 0, 'connection_error');
            throw new ModerationClientException(
                'Unable to connect to moderation service.',
                'connection_error'
            );
        } catch (Throwable $exception) {
            $this->logFailureDiagnostics($path, 0, 'service_error');
            throw new ModerationClientException(
                'Moderation request failed.',
                'service_error'
            );
        }

        if ($response->failed()) {
            $this->logFailureDiagnostics($path, $response->status(), (string) ($response->json('error.code') ?? 'service_error'));
            throw new ModerationClientException(
                (string) ($response->json('error.message') ?? 'Moderation request failed.'),
                (string) ($response->json('error.code') ?? 'service_error'),
                $response->status()
            );
        }

        return $response->json() ?? [];
    }

    private function baseRequest(): PendingRequest
    {
        return Http::baseUrl((string) config('moderation.base_url'))
            ->timeout((float) config('moderation.timeout_seconds', 8))
            ->connectTimeout((float) config('moderation.connect_timeout_seconds', 2))
            ->retry(
                (int) config('moderation.retries', 2),
                (int) config('moderation.retry_sleep_ms', 250),
                null,
                false
            )
            ->acceptJson()
            ->withHeaders([
                'X-Internal-Token' => (string) config('moderation.internal_token', ''),
            ]);
    }

    private function logRequestDiagnostics(string $path): void
    {
        if (!$this->shouldLogDiagnostics()) {
            return;
        }

        $baseUrl = rtrim((string) config('moderation.base_url'), '/');

        Log::debug('Moderation request', [
            'base_url' => $baseUrl,
            'endpoint' => $path,
            'url' => $baseUrl . $path,
            'has_internal_token' => trim((string) config('moderation.internal_token', '')) !== '',
        ]);
    }

    private function logFailureDiagnostics(string $path, int $status, string $errorCode): void
    {
        if (!$this->shouldLogDiagnostics()) {
            return;
        }

        $baseUrl = rtrim((string) config('moderation.base_url'), '/');

        Log::warning('Moderation request failed', [
            'base_url' => $baseUrl,
            'endpoint' => $path,
            'url' => $baseUrl . $path,
            'status' => $status,
            'error_code' => $errorCode,
        ]);
    }

    private function shouldLogDiagnostics(): bool
    {
        return app()->environment('local') && (bool) config('app.debug');
    }
}
