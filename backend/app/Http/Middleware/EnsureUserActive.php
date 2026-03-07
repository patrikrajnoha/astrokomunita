<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'Neautentifikovany pouzivatel',
            ], 401);
        }

        if ($user->isBanned()) {
            return response()->json([
                'message' => 'Your account has been banned.',
                'code' => 'ACCOUNT_BANNED',
                'reason' => $user->ban_reason,
                'banned_at' => optional($user->banned_at)->toIso8601String(),
            ], 403);
        }

        if (!$user->is_active) {
            return response()->json([
                'message' => 'Your account is inactive.',
                'code' => 'ACCOUNT_INACTIVE',
            ], 403);
        }

        return $next($request);
    }
}

