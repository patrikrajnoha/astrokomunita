<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CsrfTestController extends Controller
{
    public function test(Request $request): JsonResponse
    {
        return response()->json([
            'message' => 'CSRF test successful',
            'csrf_token' => $request->header('X-XSRF-TOKEN'),
            'has_cookie' => $request->hasCookie('XSRF-TOKEN'),
            'session_id' => session()->getId(),
            'timestamp' => now()->toISOString(),
        ]);
    }
}
