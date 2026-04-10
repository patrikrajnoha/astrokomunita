<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful as SanctumEnsureFrontendRequestsAreStateful;
use Laravel\Sanctum\Sanctum;

class EnsureFrontendApiRequestsAreStateful extends SanctumEnsureFrontendRequestsAreStateful
{
    public static function fromFrontend($request)
    {
        if (parent::fromFrontend($request)) {
            return true;
        }

        if (! $request instanceof Request) {
            return false;
        }

        if (static::hasExplicitXsrfSignal($request)) {
            return true;
        }

        if (! static::hasStatefulCookies($request)) {
            return false;
        }

        return static::requestHostLooksFirstParty($request);
    }

    protected static function hasExplicitXsrfSignal(Request $request): bool
    {
        return trim((string) $request->headers->get('x-xsrf-token', '')) !== ''
            || trim((string) $request->headers->get('x-csrf-token', '')) !== '';
    }

    protected static function hasStatefulCookies(Request $request): bool
    {
        $sessionCookie = trim((string) config('session.cookie', ''));
        $cookieHeader = (string) $request->headers->get('cookie', '');

        if ($request->cookies->count() > 0) {
            return true;
        }

        if ($cookieHeader === '') {
            return false;
        }

        $cookieNames = array_values(array_filter(array_unique([
            $sessionCookie,
            str_replace('-', '_', $sessionCookie),
            str_replace('_', '-', $sessionCookie),
            'XSRF-TOKEN',
        ])));

        foreach ($cookieNames as $cookieName) {
            if (str_contains($cookieHeader, $cookieName.'=')) {
                return true;
            }
        }

        return false;
    }

    protected static function requestHostLooksFirstParty(Request $request): bool
    {
        $requestHost = static::normalizeHost($request->getHttpHost());
        $requestHostNoPort = static::hostWithoutPort($requestHost);

        if ($requestHost === '' || $requestHostNoPort === '') {
            return false;
        }

        $knownHosts = Collection::make(config('sanctum.stateful', []))
            ->map(function ($host) use ($request) {
                if ($host === Sanctum::$currentRequestHostPlaceholder) {
                    return $request->getHttpHost();
                }

                return $host;
            })
            ->push(config('session.domain'))
            ->push(parse_url((string) config('app.url', ''), PHP_URL_HOST))
            ->map(static fn ($host) => static::normalizeHost((string) $host))
            ->filter()
            ->unique()
            ->values();

        return $knownHosts->contains(function (string $knownHost) use ($requestHost, $requestHostNoPort): bool {
            $knownHostNoPort = static::hostWithoutPort($knownHost);

            if ($knownHostNoPort === '') {
                return false;
            }

            return $requestHost === $knownHost
                || $requestHostNoPort === $knownHostNoPort
                || Str::endsWith($requestHostNoPort, '.'.$knownHostNoPort);
        });
    }

    protected static function normalizeHost(string $host): string
    {
        $normalized = Str::lower(trim($host));
        $normalized = preg_replace('#^https?://#', '', $normalized) ?? $normalized;
        $normalized = trim(explode('/', $normalized, 2)[0] ?? '');

        return ltrim($normalized, '.');
    }

    protected static function hostWithoutPort(string $host): string
    {
        $normalized = static::normalizeHost($host);

        return preg_replace('/:\d+$/', '', $normalized) ?? $normalized;
    }
}
