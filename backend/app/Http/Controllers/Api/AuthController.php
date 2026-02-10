<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use App\Support\UsernameRules;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class AuthController extends Controller
{
    public function me(Request $request)
    {
        return response()->json($request->user());
    }

    public function register(RegisterRequest $request)
    {
        $validated = $request->validated();

        $user = User::create([
            'name' => $validated['name'],
            'username' => $validated['username'],
            'email' => $validated['email'],
            'date_of_birth' => $validated['date_of_birth'],
            'password' => $validated['password'],
        ]);

        Auth::login($user);

        return response()->json($user, 201);
    }

    public function usernameAvailable(Request $request)
    {
        $rawUsername = (string) $request->query('username', '');
        $normalized = UsernameRules::normalize($rawUsername);

        $status = Cache::remember(
            'auth:username-availability:' . md5($normalized),
            now()->addSeconds(45),
            static fn () => UsernameRules::status($normalized)
        );

        return response()->json([
            'username' => $rawUsername,
            'normalized' => $status['normalized'],
            'available' => $status['available'],
            'reason' => $status['reason'],
        ]);
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (!Auth::attempt($credentials, true)) {
            return response()->json([
                'message' => 'Nespravny email alebo heslo.',
            ], 422);
        }

        $request->session()->regenerate();

        return response()->json($request->user());
    }

    public function logout(Request $request)
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    }
}
