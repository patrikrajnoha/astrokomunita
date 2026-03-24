<?php

namespace App\Support\Telemetry;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class SentryReporter
{
    private bool $reportingFailure = false;

    public function report(Throwable $throwable): void
    {
        if (!$this->isEnabled() || $this->reportingFailure || $this->shouldSkip($throwable)) {
            return;
        }

        $dsn = trim((string) config('telemetry.sentry.dsn', ''));
        $parsedDsn = $this->parseDsn($dsn);
        if ($parsedDsn === null) {
            return;
        }

        $this->reportingFailure = true;

        try {
            Http::timeout((float) config('telemetry.sentry.timeout_seconds', 2.5))
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'X-Sentry-Auth' => $parsedDsn['auth'],
                ])
                ->post($parsedDsn['store_url'], $this->buildPayload($throwable));
        } catch (Throwable $reportError) {
            Log::warning('Sentry telemetry send failed.', [
                'message' => $reportError->getMessage(),
            ]);
        } finally {
            $this->reportingFailure = false;
        }
    }

    private function isEnabled(): bool
    {
        if (!config('telemetry.sentry.enabled', false)) {
            return false;
        }

        if (app()->environment(['local', 'testing'])) {
            return false;
        }

        return trim((string) config('telemetry.sentry.dsn', '')) !== '';
    }

    private function shouldSkip(Throwable $throwable): bool
    {
        if ($throwable instanceof ValidationException) {
            return true;
        }

        if ($throwable instanceof HttpExceptionInterface) {
            return $throwable->getStatusCode() < 500;
        }

        return false;
    }

    /**
     * @return array{store_url:string,auth:string}|null
     */
    private function parseDsn(string $dsn): ?array
    {
        if ($dsn === '') {
            return null;
        }

        $parts = parse_url($dsn);
        if (!is_array($parts)) {
            return null;
        }

        $scheme = (string) ($parts['scheme'] ?? '');
        $host = (string) ($parts['host'] ?? '');
        $key = (string) ($parts['user'] ?? '');
        $path = trim((string) ($parts['path'] ?? ''), '/');
        if ($scheme === '' || $host === '' || $key === '' || $path === '') {
            return null;
        }

        $pathParts = explode('/', $path);
        $projectId = trim((string) end($pathParts));
        if ($projectId === '') {
            return null;
        }

        $port = isset($parts['port']) ? ':'.$parts['port'] : '';
        $secret = trim((string) ($parts['pass'] ?? ''));

        $authParts = [
            'Sentry sentry_version=7',
            'sentry_client=astrokomunita-backend/1.0',
            'sentry_key='.$key,
        ];
        if ($secret !== '') {
            $authParts[] = 'sentry_secret='.$secret;
        }

        return [
            'store_url' => sprintf('%s://%s%s/api/%s/store/', $scheme, $host, $port, $projectId),
            'auth' => implode(', ', $authParts),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function buildPayload(Throwable $throwable): array
    {
        return [
            'event_id' => bin2hex(random_bytes(16)),
            'timestamp' => gmdate('c'),
            'platform' => 'php',
            'level' => 'error',
            'logger' => 'laravel',
            'environment' => (string) config('telemetry.sentry.environment', app()->environment()),
            'release' => (string) config('telemetry.sentry.release', ''),
            'message' => $throwable->getMessage(),
            'exception' => [
                'values' => [[
                    'type' => get_class($throwable),
                    'value' => $throwable->getMessage(),
                    'stacktrace' => [
                        'frames' => $this->buildFrames($throwable),
                    ],
                ]],
            ],
            'extra' => [
                'code' => $throwable->getCode(),
                'file' => $throwable->getFile(),
                'line' => $throwable->getLine(),
            ],
            'tags' => [
                'app_env' => app()->environment(),
            ],
        ];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function buildFrames(Throwable $throwable): array
    {
        $frames = [];
        foreach ($throwable->getTrace() as $trace) {
            $frames[] = [
                'filename' => (string) ($trace['file'] ?? ''),
                'lineno' => (int) ($trace['line'] ?? 0),
                'function' => (string) ($trace['function'] ?? ''),
                'module' => (string) ($trace['class'] ?? ''),
                'in_app' => true,
            ];
        }

        $frames[] = [
            'filename' => $throwable->getFile(),
            'lineno' => $throwable->getLine(),
            'function' => '{main}',
            'module' => '',
            'in_app' => true,
        ];

        return $frames;
    }
}
