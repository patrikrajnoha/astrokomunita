<?php

namespace App\Http\Middleware;

use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUploadPayloadWithinLimit
{
    public function handle(Request $request, Closure $next): Response
    {
        $contentLength = (int) $request->server('CONTENT_LENGTH', 0);
        $postMaxSizeBytes = $this->normalizeIniSizeToBytes((string) ini_get('post_max_size'));

        if (
            $contentLength > 0
            && $postMaxSizeBytes !== null
            && $contentLength > $postMaxSizeBytes
            && $this->requestCanCarryBody($request)
        ) {
            if (!$request->is('api/*') && !$request->expectsJson()) {
                return response('Payload Too Large', 413);
            }

            return ApiResponse::error(
                sprintf(
                    'Upload je prilis velky. Maximalna velkost celej poziadavky je %s.',
                    $this->formatBytes($postMaxSizeBytes)
                ),
                null,
                413
            );
        }

        return $next($request);
    }

    private function requestCanCarryBody(Request $request): bool
    {
        return in_array($request->getMethod(), ['POST', 'PUT', 'PATCH'], true);
    }

    private function normalizeIniSizeToBytes(string $value): ?int
    {
        $trimmed = strtolower(trim($value));
        if ($trimmed === '' || $trimmed === '0') {
            return null;
        }

        if (!preg_match('/^(?<number>\d+)(?<unit>[kmg])?$/', $trimmed, $matches)) {
            return null;
        }

        $size = (int) ($matches['number'] ?? 0);
        $unit = $matches['unit'] ?? '';

        return match ($unit) {
            'g' => $size * 1024 * 1024 * 1024,
            'm' => $size * 1024 * 1024,
            'k' => $size * 1024,
            default => $size,
        };
    }

    private function formatBytes(int $bytes): string
    {
        $megabytes = (int) ceil($bytes / (1024 * 1024));

        return sprintf('%d MB', max(1, $megabytes));
    }
}
