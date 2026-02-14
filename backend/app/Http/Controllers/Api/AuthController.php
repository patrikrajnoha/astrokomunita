<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use App\Support\UsernameRules;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

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

        event(new Registered($user));
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

        $credentials['email'] = mb_strtolower(trim((string) $credentials['email']));

        try {
            $attempted = Auth::attempt($credentials, true);
        } catch (RuntimeException $exception) {
            $attempted = false;
        }

        if (! $attempted) {
            if ($this->attemptLegacyPlaintextLogin($credentials['email'], $credentials['password'])) {
                $request->session()->regenerate();
                return response()->json($request->user());
            }

            return response()->json([
                'message' => 'Nespravny email alebo heslo.',
            ], 422);
        }

        $request->session()->regenerate();

        return response()->json($request->user());
    }

    private function attemptLegacyPlaintextLogin(string $email, string $password): bool
    {
        $user = User::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->first();

        if (! $user) {
            return false;
        }

        $stored = (string) ($user->getAuthPassword() ?? '');
        if ($stored === '') {
            return false;
        }

        $hashInfo = password_get_info($stored);
        $isAlreadyHashed = ! empty($hashInfo['algo']);
        $verified = false;

        if ($isAlreadyHashed) {
            $verified = password_verify($password, $stored);
        } else {
            $verified = hash_equals($stored, $password);
        }

        if (! $verified) {
            return false;
        }

        $user->forceFill([
            'password' => Hash::make($password),
        ])->save();

        Auth::login($user, true);

        return true;
    }

    public function logout(Request $request)
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    }

    public function verifyEmail(Request $request, int $id, string $hash)
    {
        if (!$request->hasValidSignature()) {
            return response()->json([
                'message' => 'Verification link is invalid or expired.',
            ], 403);
        }

        $user = User::query()->find($id);
        if (!$user) {
            return response()->json([
                'message' => 'Verification link is invalid.',
            ], 404);
        }

        if (!hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
            return response()->json([
                'message' => 'Verification link is invalid.',
            ], 403);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Email already verified.',
            ]);
        }

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        return response()->json([
            'message' => 'Email verified successfully.',
        ]);
    }

    public function resendVerificationEmail(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Email already verified.',
            ]);
        }

        $user->sendEmailVerificationNotification();

        return response()->json([
            'message' => 'Verification link sent.',
        ]);
    }
}
