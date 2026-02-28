<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Cache\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class SkyThrottle
{
    public function __construct(
        private readonly RateLimiter $rateLimiter
    ) {
    }

    public function handle(Request $request, Closure $next, string $bucket): Response
    {
        if ($this->shouldBypass($request)) {
            return $next($request);
        }

        $limit = $this->resolveLimit($request, $this->resolveLimiterName($request, $bucket));

        if ($this->rateLimiter->tooManyAttempts($limit->key, $limit->maxAttempts)) {
            return $this->buildRateLimitedResponse($limit);
        }

        $attempts = $this->rateLimiter->hit($limit->key, $limit->decaySeconds);
        $response = $next($request);

        return $this->addHeaders($response, $limit->maxAttempts, max(0, $limit->maxAttempts - $attempts));
    }

    private function shouldBypass(Request $request): bool
    {
        if (app()->runningInConsole() && !app()->runningUnitTests()) {
            return true;
        }

        $configuredToken = trim((string) config('observing.sky.internal_token', ''));
        $providedToken = trim((string) $request->header('X-Internal-Token', ''));

        return $configuredToken !== '' && $providedToken !== '' && hash_equals($configuredToken, $providedToken);
    }

    private function resolveUserId(Request $request): ?int
    {
        return $request->user('sanctum')?->id ?? $request->user()?->id;
    }

    private function resolveLimiterName(Request $request, string $bucket): string
    {
        $suffix = $this->resolveUserId($request) !== null ? 'auth' : 'guest';

        return sprintf('sky-%s-%s', $bucket, $suffix);
    }

    private function resolveLimit(Request $request, string $limiterName): Limit
    {
        $limiter = $this->rateLimiter->limiter($limiterName);

        if (!is_callable($limiter)) {
            throw new RuntimeException(sprintf('Missing sky rate limiter [%s].', $limiterName));
        }

        $resolved = $limiter($request);
        $limit = is_array($resolved) ? reset($resolved) : $resolved;

        if (!$limit instanceof Limit) {
            throw new RuntimeException(sprintf('Invalid sky rate limiter [%s] definition.', $limiterName));
        }

        return $limit;
    }

    private function buildRateLimitedResponse(Limit $limit): JsonResponse
    {
        $retryAfter = $this->rateLimiter->availableIn($limit->key);

        return $this->addHeaders(
            response()->json([
                'success' => false,
                'message' => 'Príliš veľa požiadaviek. Skús znova o chvíľu.',
            ], 429),
            $limit->maxAttempts,
            0,
            $retryAfter
        );
    }

    private function addHeaders(Response $response, int $maxAttempts, int $remainingAttempts, ?int $retryAfter = null): Response
    {
        $headers = [
            'X-RateLimit-Limit' => $maxAttempts,
            'X-RateLimit-Remaining' => $remainingAttempts,
        ];

        if ($retryAfter !== null) {
            $headers['Retry-After'] = $retryAfter;
            $headers['X-RateLimit-Reset'] = now()->addSeconds($retryAfter)->getTimestamp();
        }

        $response->headers->add($headers);

        return $response;
    }
}
