<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function me(Request $request)
    {
        return response()->json($request->user());
    }

    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            // DEV friendly: no "dns" so test.local addresses pass
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'username' => $this->generateUsername($validated['name'], $validated['email']),
            'email' => $validated['email'],
            // User model has cast: 'password' => 'hashed' -> auto hash
            'password' => $validated['password'],
        ]);

        Auth::login($user);

        return response()->json($user, 201);
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

    private function generateUsername(string $name, string $email): string
    {
        $base = trim($name) !== '' ? $name : $email;
        $candidate = strtolower($base);
        $candidate = preg_replace('/\s+/', '_', $candidate);
        $candidate = preg_replace('/[^a-z0-9_]+/', '', $candidate);
        $candidate = substr($candidate, 0, 30);
        if ($candidate === '') {
            $candidate = 'user';
        }

        $username = $candidate;
        $i = 1;
        while (User::where('username', $username)->exists()) {
            $suffix = '_' . $i;
            $username = substr($candidate, 0, 30 - strlen($suffix)) . $suffix;
            $i++;
        }

        return $username;
    }
}
