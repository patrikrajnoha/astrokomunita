<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureEmailIsVerifiedOrAdmin extends EnsureEmailIsVerified
{
    /**
     * Allow admins to pass without verified email, keep default behavior for everyone else.
     */
    public function handle($request, Closure $next, $redirectToRoute = null): Response
    {
        $user = $request instanceof Request ? $request->user() : null;

        if ($user && method_exists($user, 'isAdmin') && $user->isAdmin()) {
            return $next($request);
        }

        return parent::handle($request, $next, $redirectToRoute);
    }
}

