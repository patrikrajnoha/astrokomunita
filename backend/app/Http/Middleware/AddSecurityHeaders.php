<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AddSecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        if (! (bool) config('security.headers.enabled', true)) {
            return $response;
        }

        $response->headers->set('X-Frame-Options', (string) config('security.headers.x_frame_options', 'SAMEORIGIN'));
        $response->headers->set('X-Content-Type-Options', (string) config('security.headers.x_content_type_options', 'nosniff'));
        $response->headers->set('Referrer-Policy', (string) config('security.headers.referrer_policy', 'strict-origin-when-cross-origin'));
        $response->headers->set('Permissions-Policy', (string) config('security.headers.permissions_policy', 'camera=(), geolocation=(), microphone=()'));
        $response->headers->set('X-Permitted-Cross-Domain-Policies', (string) config('security.headers.x_permitted_cross_domain_policies', 'none'));

        $hstsMaxAge = (int) config('security.headers.hsts_max_age', 31536000);
        if ($hstsMaxAge > 0 && ($request->isSecure() || (bool) config('session.secure', false))) {
            $hstsValue = 'max-age=' . $hstsMaxAge;

            if ((bool) config('security.headers.hsts_include_subdomains', true)) {
                $hstsValue .= '; includeSubDomains';
            }

            if ((bool) config('security.headers.hsts_preload', false)) {
                $hstsValue .= '; preload';
            }

            $response->headers->set('Strict-Transport-Security', $hstsValue);
        }

        return $response;
    }
}
