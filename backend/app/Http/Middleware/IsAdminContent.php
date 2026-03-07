<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IsAdminContent
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'message' => 'Neautentifikovany pouzivatel',
            ], 401);
        }

        if ($user->isAdmin() || $user->isEditor()) {
            return $next($request);
        }

        return response()->json([
            'message' => 'Zakazane',
        ], 403);
    }
}

