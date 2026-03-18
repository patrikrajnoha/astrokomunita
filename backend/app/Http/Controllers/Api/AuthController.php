<?php

namespace App\Http\Controllers\Api;

use Database\Seeders\DefaultUsersSeeder;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use App\Services\Auth\EmailVerificationSettingService;
use App\Services\Security\TurnstileService;
use App\Services\UserActivityService;
use App\Support\UsernameRules;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class AuthController extends Controller
{
    public function __construct(
        private readonly UserActivityService $activityService,
        private readonly TurnstileService $turnstileService,
        private readonly EmailVerificationSettingService $emailVerificationSettingService,
    ) {
    }

    public function me(Request $request)
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(null);
        }

        if ($user->isBanned()) {
            return response()->json([
                'message' => 'Your account has been banned.',
                'code' => 'ACCOUNT_BANNED',
                'reason' => $user->ban_reason,
                'banned_at' => optional($user->banned_at)->toIso8601String(),
            ], 403);
        }

        if (! $user->is_active) {
            return response()->json([
                'message' => 'Your account is inactive.',
                'code' => 'ACCOUNT_INACTIVE',
            ], 403);
        }

        try {
            $activity = $this->activityService->getActivity($user);
        } catch (\Throwable) {
            $activity = null;
        }

        return response()->json([
            ...$user->toArray(),
            'activity' => $activity,
        ]);
    }

    public function register(RegisterRequest $request)
    {
        $validated = $request->validated();
        $requiresEmailVerification = $this->emailVerificationSettingService
            ->requiresEmailVerificationForNewUsers();

        if ($this->turnstileService->isEnabled() && ! $this->turnstileService->hasSecretKey()) {
            $this->turnstileService->logMissingSecretWarningOnce();

            return response()->json([
                'message' => 'Bezpečnostné overenie je dočasne nedostupné.',
            ], 503);
        }

        $turnstileToken = (string) ($validated['turnstile_token'] ?? '');
        if (! $this->turnstileService->verify($turnstileToken, $request->ip())) {
            throw ValidationException::withMessages([
                'turnstile_token' => 'Overenie proti botom zlyhalo. Skus to prosim znova.',
            ]);
        }

        $user = User::create([
            'name' => $validated['name'],
            'username' => $validated['username'],
            'email' => $validated['email'],
            'date_of_birth' => $validated['date_of_birth'],
            'password' => $validated['password'],
            'requires_email_verification' => $requiresEmailVerification,
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
        $this->ensureDefaultUsersForLocal();

        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['sometimes', 'boolean'],
        ]);
        $remember = (bool) ($credentials['remember'] ?? false);
        unset($credentials['remember']);

        $credentials['email'] = mb_strtolower(trim((string) $credentials['email']));

        $isBotAccount = User::query()
            ->whereRaw('LOWER(email) = ?', [$credentials['email']])
            ->where('is_bot', true)
            ->exists();

        if ($isBotAccount) {
            return response()->json([
                'message' => 'Nespravny email alebo heslo.',
            ], 422);
        }

        try {
            $attempted = Auth::attempt($credentials, $remember);
        } catch (RuntimeException $exception) {
            $attempted = false;
        }

        if (! $attempted) {
            if ($this->attemptLegacyPlaintextLogin($credentials['email'], $credentials['password'], $remember)) {
                $request->session()->regenerate();

                $loggedInUser = $request->user();
                if ($loggedInUser && ($loggedInUser->isBanned() || ! $loggedInUser->is_active)) {
                    Auth::guard('web')->logoutCurrentDevice();
                    return response()->json(['message' => 'Váš účet je zablokovaný.'], 403);
                }

                return response()->json($loggedInUser);
            }

            return response()->json([
                'message' => 'Nespravny email alebo heslo.',
            ], 422);
        }

        $request->session()->regenerate();

        $loggedInUser = $request->user();
        if ($loggedInUser && ($loggedInUser->isBanned() || ! $loggedInUser->is_active)) {
            Auth::guard('web')->logoutCurrentDevice();
            return response()->json(['message' => 'Váš účet je zablokovaný.'], 403);
        }

        return response()->json($loggedInUser);
    }

    private function ensureDefaultUsersForLocal(): void
    {
        if (! app()->environment('local')) {
            return;
        }

        $hasLocalAdmin = User::query()
            ->whereRaw('LOWER(email) = ?', ['admin@admin.sk'])
            ->orWhere('username', 'admin')
            ->exists();

        if ($hasLocalAdmin) {
            return;
        }

        try {
            app(DefaultUsersSeeder::class)->seed();
        } catch (\Throwable $error) {
            Log::warning('Failed to auto-seed default users before login.', [
                'message' => $error->getMessage(),
            ]);
        }
    }

    private function attemptLegacyPlaintextLogin(string $email, string $password, bool $remember): bool
    {
        if (! $this->legacyPlaintextFallbackAllowed()) {
            return false;
        }

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

        $this->logLegacyPlaintextFallbackUsage($user);

        $user->forceFill([
            'password' => Hash::make($password),
        ])->save();

        Auth::login($user, $remember);

        return true;
    }

    private function legacyPlaintextFallbackAllowed(): bool
    {
        if (! (bool) config('auth.legacy_plaintext_enabled', false)) {
            return false;
        }

        if (app()->environment('local')) {
            return true;
        }

        return (bool) config('auth.legacy_plaintext_allow_non_local', false);
    }

    private function logLegacyPlaintextFallbackUsage(User $user): void
    {
        $cacheKey = 'auth:legacy-plaintext-fallback-warning:' . $user->id;
        $shouldLog = Cache::add($cacheKey, true, now()->addHours(6));

        if (! $shouldLog) {
            return;
        }

        Log::warning('AUTH_LEGACY_PLAINTEXT_FALLBACK_USED', [
            'user_id' => $user->id,
            'environment' => app()->environment(),
            'marker' => 'auth_legacy_plaintext_fallback_used',
        ]);
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
        // SECURITY: Fail-fast 410 must stay first to prevent email verification bypass.
        if (! (bool) config('auth.enable_signed_link_email_verification', false)) {
            return $this->deprecatedSignedLinkResponse();
        }

        if (! $request->hasValidSignature()) {
            return response()->json([
                'message' => 'Overovací odkaz je neplatný alebo expirovaný.',
            ], 403);
        }

        $user = User::query()->find($id);
        if (! $user) {
            return response()->json([
                'message' => 'Overovaci odkaz je neplatny.',
            ], 404);
        }

        if (! hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
            return response()->json([
                'message' => 'Overovaci odkaz je neplatny.',
            ], 403);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'E-mail je uz overeny.',
            ]);
        }

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        return response()->json([
            'message' => 'E-mail bol uspesne overeny.',
        ]);
    }

    public function resendVerificationEmail(Request $request)
    {
        if (! (bool) config('auth.enable_signed_link_email_verification', false)) {
            return $this->deprecatedSignedLinkResponse();
        }

        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'Neautentifikovaný používateľ.',
            ], 401);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'E-mail je uz overeny.',
            ]);
        }

        $user->sendEmailVerificationNotification();

        return response()->json([
            'message' => 'Overovaci odkaz bol odoslany.',
        ]);
    }

    private function deprecatedSignedLinkResponse()
    {
        return response()->json([
            'error_code' => 'EMAIL_VERIFY_DEPRECATED',
            'message' => 'Overenie cez odkaz je zastarane. Pouzite overenie kodom v Nastaveniach.',
            'action' => 'GO_TO_SETTINGS_EMAIL',
        ], 410);
    }
}

