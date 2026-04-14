<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\PasswordResetCodeMail;
use App\Models\EmailVerification;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class PasswordResetController extends Controller
{
    public function sendCode(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        $email = $this->normalizeEmail((string) $validated['email']);
        $genericMessage = 'Ak účet existuje, poslali sme obnovovací kód na váš e-mail.';

        if ($email === '') {
            return response()->json(['message' => $genericMessage]);
        }

        $user = $this->findResettableUserByEmail($email);
        if (! $user) {
            return response()->json(['message' => $genericMessage]);
        }

        if (! $this->canSendCode($user, $email, (string) $request->ip())) {
            return response()->json(['message' => $genericMessage]);
        }

        $formattedCode = $this->generateFormattedCode();
        $normalizedCode = $this->normalizeCode($formattedCode);
        $verification = $this->latestPasswordResetVerification($user, $email);

        if (! $verification) {
            $verification = new EmailVerification([
                'user_id' => $user->id,
                'email' => $email,
                'purpose' => EmailVerification::PURPOSE_PASSWORD_RESET,
            ]);
        }

        $wasExistingVerification = $verification->exists;
        $previousVerificationState = $wasExistingVerification
            ? [
                'code_hash' => $verification->code_hash,
                'expires_at' => $verification->expires_at?->copy(),
                'consumed_at' => $verification->consumed_at?->copy(),
                'attempts' => (int) $verification->attempts,
                'last_sent_at' => $verification->last_sent_at?->copy(),
            ]
            : null;

        $verification->forceFill([
            'code_hash' => Hash::make($normalizedCode),
            'expires_at' => now()->addMinutes($this->codeTtlMinutes()),
            'consumed_at' => null,
            'attempts' => 0,
            'last_sent_at' => now(),
        ])->save();

        try {
            Mail::to($email)->send(new PasswordResetCodeMail($formattedCode));
            RateLimiter::hit($this->sendRateKey($email, (string) $request->ip()), 3600);
        } catch (\Throwable $error) {
            $this->rollbackVerificationAfterMailFailure($verification, $previousVerificationState, $wasExistingVerification);

            Log::error('Password reset code email send failed.', [
                'user_id' => $user->id,
                'message' => $error->getMessage(),
                'mailer' => (string) config('mail.default'),
                'mail_host' => (string) config('mail.mailers.smtp.host'),
                'mail_port' => (int) config('mail.mailers.smtp.port'),
                'mail_from' => (string) config('mail.verification_from.address'),
                'delivery_mode' => 'sync',
            ]);

            return response()->json([
                'message' => 'Obnovovací kód sa nepodarilo odoslať. Skúste to znova neskôr.',
                'error_code' => 'PASSWORD_RESET_DELIVERY_FAILED',
            ], 503);
        }

        return response()->json(['message' => $genericMessage]);
    }

    public function reset(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'code' => ['required', 'string', 'max:32'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $email = $this->normalizeEmail((string) $validated['email']);
        $rawCode = (string) $validated['code'];
        $normalizedCode = $this->normalizeCode($rawCode);

        if ($email === '' || $normalizedCode === '' || ! $this->looksLikeCodeFormat($rawCode)) {
            return $this->invalidCodeResponse();
        }

        $confirmRateKey = $this->confirmRateKey($email, (string) $request->ip());
        if (RateLimiter::tooManyAttempts($confirmRateKey, $this->maxConfirmAttemptsPerWindow())) {
            return response()->json([
                'message' => 'Príliš veľa pokusov o reset. Skúste to neskôr.',
                'error_code' => 'PASSWORD_RESET_ATTEMPTS_EXCEEDED',
            ], 429);
        }
        RateLimiter::hit($confirmRateKey, $this->confirmWindowSeconds());

        $user = $this->findResettableUserByEmail($email);
        if (! $user) {
            return $this->invalidCodeResponse();
        }

        $verification = $this->latestPasswordResetVerification($user, $email, true);
        if (! $verification) {
            return $this->invalidCodeResponse();
        }

        if ($verification->expires_at === null || now()->greaterThan($verification->expires_at)) {
            return response()->json([
                'message' => 'Platnosť obnovovacieho kódu vypršala. Vyžiadajte si nový.',
                'error_code' => 'PASSWORD_RESET_CODE_EXPIRED',
            ], 422);
        }

        if ((int) $verification->attempts >= $this->maxConfirmAttemptsPerToken()) {
            return response()->json([
                'message' => 'Príliš veľa pokusov pre tento obnovovací kód.',
                'error_code' => 'PASSWORD_RESET_CODE_ATTEMPTS_EXCEEDED',
            ], 429);
        }

        if (! Hash::check($normalizedCode, (string) $verification->code_hash)) {
            $verification->increment('attempts');
            $verification->refresh();

            if ((int) $verification->attempts >= $this->maxConfirmAttemptsPerToken()) {
                return response()->json([
                    'message' => 'Príliš veľa pokusov pre tento obnovovací kód.',
                    'error_code' => 'PASSWORD_RESET_CODE_ATTEMPTS_EXCEEDED',
                ], 429);
            }

            return $this->invalidCodeResponse();
        }

        DB::transaction(function () use ($user, $verification, $validated): void {
            $user->forceFill([
                'password' => Hash::make((string) $validated['password']),
                'remember_token' => Str::random(60),
            ])->save();

            $verification->forceFill([
                'consumed_at' => now(),
            ])->save();

            EmailVerification::query()
                ->where('id', '!=', $verification->id)
                ->where('user_id', $user->id)
                ->whereRaw('LOWER(email) = ?', [strtolower((string) $user->email)])
                ->where('purpose', EmailVerification::PURPOSE_PASSWORD_RESET)
                ->whereNull('consumed_at')
                ->update([
                    'consumed_at' => now(),
                ]);
        });

        return response()->json([
            'message' => 'Heslo bolo úspešne obnovené. Teraz sa môžete prihlásiť.',
        ]);
    }

    private function invalidCodeResponse(): JsonResponse
    {
        return response()->json([
            'message' => 'Zadali ste neplatný kód. Mal by mať tvar XXXXX-XXXXX.',
            'error_code' => 'PASSWORD_RESET_CODE_INVALID',
        ], 422);
    }

    private function canSendCode(User $user, string $email, string $ip): bool
    {
        $rateKey = $this->sendRateKey($email, $ip);
        if (RateLimiter::tooManyAttempts($rateKey, $this->maxSendPerHour())) {
            return false;
        }

        $verification = $this->latestPasswordResetVerification($user, $email);
        if (! $verification?->last_sent_at) {
            return true;
        }

        return now()->greaterThanOrEqualTo(
            $verification->last_sent_at->copy()->addSeconds($this->resendCooldownSeconds())
        );
    }

    private function latestPasswordResetVerification(
        User $user,
        string $email,
        bool $includeExpired = false,
    ): ?EmailVerification {
        $query = EmailVerification::query()
            ->where('user_id', $user->id)
            ->whereRaw('LOWER(email) = ?', [$email])
            ->where('purpose', EmailVerification::PURPOSE_PASSWORD_RESET)
            ->whereNull('consumed_at');

        if (! $includeExpired) {
            $query->where('expires_at', '>', now());
        }

        return $query->latest('id')->first();
    }

    private function findResettableUserByEmail(string $email): ?User
    {
        return User::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->where('is_active', true)
            ->where('is_banned', false)
            ->where('is_bot', false)
            ->first();
    }

    private function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    private function normalizeCode(string $code): string
    {
        $upper = mb_strtoupper(trim($code));
        $normalized = preg_replace('/[^A-Z0-9]/', '', $upper);

        return is_string($normalized) ? $normalized : '';
    }

    private function looksLikeCodeFormat(string $code): bool
    {
        return (bool) preg_match('/^[A-Z0-9]{5}-[A-Z0-9]{5}$/', mb_strtoupper(trim($code)));
    }

    private function generateFormattedCode(): string
    {
        $digits = '';
        for ($i = 0; $i < 10; $i++) {
            $digits .= (string) random_int(0, 9);
        }

        return substr($digits, 0, 5) . '-' . substr($digits, 5, 5);
    }

    private function codeTtlMinutes(): int
    {
        return max(5, (int) config('password_reset.code_ttl_minutes', 20));
    }

    private function resendCooldownSeconds(): int
    {
        return max(10, (int) config('password_reset.resend_cooldown_seconds', 60));
    }

    private function maxSendPerHour(): int
    {
        return max(1, (int) config('password_reset.max_send_per_hour', 5));
    }

    private function maxConfirmAttemptsPerToken(): int
    {
        return max(1, (int) config('password_reset.max_confirm_attempts_per_token', 8));
    }

    private function maxConfirmAttemptsPerWindow(): int
    {
        return max(1, (int) config('password_reset.max_confirm_attempts_per_window', 10));
    }

    private function confirmWindowSeconds(): int
    {
        return max(60, (int) config('password_reset.confirm_window_seconds', 900));
    }

    private function sendRateKey(string $email, string $ip): string
    {
        return sprintf('password-reset:send:%s:%s', sha1($email), $ip);
    }

    private function confirmRateKey(string $email, string $ip): string
    {
        return sprintf('password-reset:confirm:%s:%s', sha1($email), $ip);
    }

    /**
     * @param array{code_hash:mixed,expires_at:mixed,consumed_at:mixed,attempts:int,last_sent_at:mixed}|null $previousVerificationState
     */
    private function rollbackVerificationAfterMailFailure(
        EmailVerification $verification,
        ?array $previousVerificationState,
        bool $wasExistingVerification,
    ): void {
        try {
            if (! $wasExistingVerification) {
                if ($verification->exists) {
                    $verification->delete();
                }

                return;
            }

            if ($previousVerificationState === null) {
                return;
            }

            $verification->forceFill([
                'code_hash' => $previousVerificationState['code_hash'],
                'expires_at' => $previousVerificationState['expires_at'],
                'consumed_at' => $previousVerificationState['consumed_at'],
                'attempts' => $previousVerificationState['attempts'],
                'last_sent_at' => $previousVerificationState['last_sent_at'],
            ])->save();
        } catch (\Throwable $rollbackError) {
            Log::warning('Password reset verification rollback failed.', [
                'verification_id' => $verification->id,
                'message' => $rollbackError->getMessage(),
            ]);
        }
    }
}
