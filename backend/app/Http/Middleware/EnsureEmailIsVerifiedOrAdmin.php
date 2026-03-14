<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureEmailIsVerifiedOrAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'message' => 'Neautentifikovany pouzivatel.',
            ], 401);
        }

        if (method_exists($user, 'isAdmin') && $user->isAdmin()) {
            return $next($request);
        }

        if ($user->hasVerifiedEmail()) {
            return $next($request);
        }

        return response()->json([
            'message' => 'E-mailova adresa nie je overena.',
            'error_code' => 'EMAIL_NOT_VERIFIED',
            'action' => 'GO_TO_SETTINGS_EMAIL',
        ], 403);
    }
}
