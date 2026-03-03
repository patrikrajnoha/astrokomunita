<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\EmailVerificationMail;
use App\Models\EmailChangeRequest;
use App\Models\EmailVerification;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;

class AccountEmailController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'data' => $this->statusPayload($user),
        ]);
    }

    public function sendVerificationCode(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $email = $this->normalizeEmail((string) ($user->email ?? ''));

        if ($email === '') {
            return $this->errorResponse('User has no email address.', 'EMAIL_MISSING', 422);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Email already verified.',
                'data' => $this->statusPayload($user),
            ]);
        }

        $pendingApplied = $this->activePendingChangeRequest($user);
        $pendingAppliedId = ($pendingApplied && $pendingApplied->new_email_applied_at !== null)
            ? (int) $pendingApplied->id
            : null;

        $sent = $this->issueVerificationCode(
            $user,
            $email,
            EmailVerification::PURPOSE_ACCOUNT_VERIFICATION,
            $pendingAppliedId
        );

        if (! $sent['ok']) {
            return $this->errorResponse(
                (string) $sent['message'],
                (string) $sent['error_code'],
                (int) $sent['status'],
                $sent['meta'] ?? null,
            );
        }

        return response()->json([
            'message' => 'Verification code sent.',
            'data' => array_merge(
                $this->statusPayload($user->fresh()),
                [
                    'seconds_to_resend' => $sent['seconds_to_resend'],
                    'expires_at' => $sent['expires_at'],
                ]
            ),
        ]);
    }

    public function confirmVerificationCode(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:32'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $email = $this->normalizeEmail((string) ($user->email ?? ''));

        if ($email === '') {
            return $this->errorResponse('User has no email address.', 'EMAIL_MISSING', 422);
        }

        $pendingApplied = $this->activePendingChangeRequest($user);
        $pendingAppliedId = ($pendingApplied && $pendingApplied->new_email_applied_at !== null)
            ? (int) $pendingApplied->id
            : null;

        $confirmed = $this->consumeVerificationCode(
            $user,
            $email,
            EmailVerification::PURPOSE_ACCOUNT_VERIFICATION,
            (string) $validated['code'],
            $pendingAppliedId,
            true
        );

        if (! $confirmed['ok']) {
            return $this->errorResponse(
                (string) $confirmed['message'],
                (string) $confirmed['error_code'],
                (int) $confirmed['status'],
                $confirmed['meta'] ?? null,
            );
        }

        if (! $user->hasVerifiedEmail()) {
            $user->forceFill([
                'email_verified_at' => now(),
            ])->save();
        }

        EmailChangeRequest::query()
            ->where('user_id', $user->id)
            ->whereRaw('LOWER(new_email) = ?', [$email])
            ->whereNotNull('new_email_applied_at')
            ->whereNull('completed_at')
            ->whereNull('cancelled_at')
            ->update([
                'completed_at' => now(),
            ]);

        return response()->json([
            'message' => 'Email verified successfully.',
            'data' => $this->statusPayload($user->fresh()),
        ]);
    }

    public function requestEmailChange(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'new_email' => ['required', 'email', 'max:255'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $currentEmail = $this->normalizeEmail((string) ($user->email ?? ''));
        $newEmail = $this->normalizeEmail((string) $validated['new_email']);

        if ($newEmail === '') {
            return $this->errorResponse('New email is required.', 'NEW_EMAIL_REQUIRED', 422);
        }

        if ($newEmail === $currentEmail) {
            return $this->errorResponse('New email must be different from current email.', 'EMAIL_SAME_AS_CURRENT', 422);
        }

        $emailTaken = User::query()
            ->where('id', '!=', $user->id)
            ->whereRaw('LOWER(email) = ?', [$newEmail])
            ->exists();

        if ($emailTaken) {
            return $this->errorResponse('Email is already in use.', 'EMAIL_ALREADY_IN_USE', 422, [
                'errors' => [
                    'new_email' => ['Email is already in use.'],
                ],
            ]);
        }

        $this->cancelPendingEmailChanges($user);

        if ($user->hasVerifiedEmail()) {
            EmailChangeRequest::query()->create([
                'user_id' => $user->id,
                'current_email' => $currentEmail,
                'new_email' => $newEmail,
                'expires_at' => now()->addMinutes($this->emailChangeTtlMinutes()),
            ]);

            return response()->json([
                'message' => 'Confirm your current email before applying the new email.',
                'data' => $this->statusPayload($user->fresh()),
            ]);
        }

        $user->forceFill([
            'email' => $newEmail,
            'email_verified_at' => null,
        ])->save();

        $sent = $this->issueVerificationCode(
            $user->fresh(),
            $newEmail,
            EmailVerification::PURPOSE_ACCOUNT_VERIFICATION
        );

        if (! $sent['ok']) {
            return $this->errorResponse(
                (string) $sent['message'],
                (string) $sent['error_code'],
                (int) $sent['status'],
                $sent['meta'] ?? null,
            );
        }

        return response()->json([
            'message' => 'Email updated. Verify the new email address.',
            'data' => array_merge(
                $this->statusPayload($user->fresh()),
                [
                    'seconds_to_resend' => $sent['seconds_to_resend'],
                    'expires_at' => $sent['expires_at'],
                ]
            ),
        ]);
    }

    public function confirmCurrentEmailChange(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['nullable', 'string', 'max:32'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $pending = $this->activePendingChangeRequest($user);

        if (! $pending) {
            return $this->errorResponse('No pending email change request found.', 'EMAIL_CHANGE_NOT_PENDING', 422);
        }

        if ($pending->new_email_applied_at !== null) {
            return response()->json([
                'message' => 'Current email already confirmed.',
                'data' => $this->statusPayload($user->fresh()),
            ]);
        }

        $code = trim((string) ($validated['code'] ?? ''));
        if ($code === '') {
            $sent = $this->issueVerificationCode(
                $user,
                (string) $pending->current_email,
                EmailVerification::PURPOSE_EMAIL_CHANGE_CURRENT,
                (int) $pending->id
            );

            if (! $sent['ok']) {
                return $this->errorResponse(
                    (string) $sent['message'],
                    (string) $sent['error_code'],
                    (int) $sent['status'],
                    $sent['meta'] ?? null,
                );
            }

            return response()->json([
                'message' => 'Confirmation code sent to your current email.',
                'data' => array_merge(
                    $this->statusPayload($user->fresh()),
                    [
                        'seconds_to_resend' => $sent['seconds_to_resend'],
                        'expires_at' => $sent['expires_at'],
                    ]
                ),
            ]);
        }

        $confirmed = $this->consumeVerificationCode(
            $user,
            (string) $pending->current_email,
            EmailVerification::PURPOSE_EMAIL_CHANGE_CURRENT,
            $code,
            (int) $pending->id
        );

        if (! $confirmed['ok']) {
            return $this->errorResponse(
                (string) $confirmed['message'],
                (string) $confirmed['error_code'],
                (int) $confirmed['status'],
                $confirmed['meta'] ?? null,
            );
        }

        $pending->forceFill([
            'current_email_confirmed_at' => now(),
        ])->save();

        return response()->json([
            'message' => 'Current email confirmed.',
            'data' => $this->statusPayload($user->fresh()),
        ]);
    }

    public function confirmNewEmailChange(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $pending = $this->activePendingChangeRequest($user);

        if (! $pending) {
            return $this->errorResponse('No pending email change request found.', 'EMAIL_CHANGE_NOT_PENDING', 422);
        }

        if ($pending->new_email_applied_at !== null) {
            return response()->json([
                'message' => 'New email is already applied. Verify it with the email verification code.',
                'data' => $this->statusPayload($user->fresh()),
            ]);
        }

        if ($pending->current_email_confirmed_at === null) {
            return $this->errorResponse(
                'Confirm your current email first.',
                'EMAIL_CHANGE_CURRENT_CONFIRMATION_REQUIRED',
                422
            );
        }

        DB::transaction(function () use ($user, $pending): void {
            $user->forceFill([
                'email' => (string) $pending->new_email,
                'email_verified_at' => null,
            ])->save();

            $pending->forceFill([
                'new_email_applied_at' => now(),
            ])->save();
        });

        $sent = $this->issueVerificationCode(
            $user->fresh(),
            (string) $pending->new_email,
            EmailVerification::PURPOSE_ACCOUNT_VERIFICATION,
            (int) $pending->id
        );

        if (! $sent['ok']) {
            return $this->errorResponse(
                (string) $sent['message'],
                (string) $sent['error_code'],
                (int) $sent['status'],
                $sent['meta'] ?? null,
            );
        }

        return response()->json([
            'message' => 'New email applied. Verify it with the code sent to your new email address.',
            'data' => array_merge(
                $this->statusPayload($user->fresh()),
                [
                    'seconds_to_resend' => $sent['seconds_to_resend'],
                    'expires_at' => $sent['expires_at'],
                ]
            ),
        ]);
    }

    /**
     * @return array{ok:bool,status?:int,error_code?:string,message?:string,meta?:array<string,mixed>,seconds_to_resend?:int,expires_at?:string}
     */
    private function issueVerificationCode(
        User $user,
        string $email,
        string $purpose,
        ?int $emailChangeRequestId = null,
    ): array {
        $normalizedEmail = $this->normalizeEmail($email);
        if ($normalizedEmail === '') {
            return [
                'ok' => false,
                'status' => 422,
                'error_code' => 'EMAIL_MISSING',
                'message' => 'User has no email address.',
            ];
        }

        $rateKey = $this->sendRateKey($user, $normalizedEmail, $purpose, $emailChangeRequestId);
        $maxSendPerHour = $this->maxSendPerHour();

        if (RateLimiter::tooManyAttempts($rateKey, $maxSendPerHour)) {
            return [
                'ok' => false,
                'status' => 429,
                'error_code' => 'EMAIL_VERIFICATION_SEND_RATE_LIMIT',
                'message' => 'Too many code send attempts. Try again later.',
                'meta' => [
                    'retry_after_seconds' => RateLimiter::availableIn($rateKey),
                ],
            ];
        }

        $secondsToResend = $this->secondsToResend($user, $normalizedEmail, $purpose, $emailChangeRequestId);
        if ($secondsToResend > 0) {
            return [
                'ok' => false,
                'status' => 429,
                'error_code' => 'EMAIL_VERIFICATION_RESEND_COOLDOWN',
                'message' => 'Please wait before requesting another code.',
                'meta' => [
                    'seconds_to_resend' => $secondsToResend,
                ],
            ];
        }

        $formattedCode = $this->generateFormattedCode();
        $normalizedCode = $this->normalizeCode($formattedCode);

        $verification = $this->activeVerificationQuery($user, $normalizedEmail, $purpose, $emailChangeRequestId)
            ->latest('id')
            ->first();

        if (! $verification) {
            $verification = new EmailVerification([
                'user_id' => $user->id,
                'email_change_request_id' => $emailChangeRequestId,
                'email' => $normalizedEmail,
                'purpose' => $purpose,
            ]);
        }

        $verification->forceFill([
            'code_hash' => Hash::make($normalizedCode),
            'expires_at' => now()->addMinutes($this->codeTtlMinutes()),
            'consumed_at' => null,
            'attempts' => 0,
            'last_sent_at' => now(),
        ])->save();

        try {
            Mail::to($normalizedEmail)->send(new EmailVerificationMail($formattedCode, $purpose));
        } catch (\Throwable $error) {
            return [
                'ok' => false,
                'status' => 500,
                'error_code' => 'EMAIL_VERIFICATION_SEND_FAILED',
                'message' => 'Failed to send verification email.',
            ];
        }

        RateLimiter::hit($rateKey, 3600);

        return [
            'ok' => true,
            'seconds_to_resend' => $this->resendCooldownSeconds(),
            'expires_at' => optional($verification->expires_at)->toIso8601String(),
        ];
    }

    /**
     * @return array{ok:bool,status?:int,error_code?:string,message?:string,meta?:array<string,mixed>}
     */
    private function consumeVerificationCode(
        User $user,
        string $email,
        string $purpose,
        string $rawCode,
        ?int $emailChangeRequestId = null,
        bool $allowFallbackWithoutChangeRequestId = false,
    ): array {
        $normalizedEmail = $this->normalizeEmail($email);
        $normalizedCode = $this->normalizeCode($rawCode);

        if ($normalizedCode === '') {
            return [
                'ok' => false,
                'status' => 422,
                'error_code' => 'EMAIL_VERIFICATION_CODE_INVALID',
                'message' => 'Verification code is invalid.',
            ];
        }

        $rateKey = $this->confirmRateKey($user, $purpose);
        if (RateLimiter::tooManyAttempts($rateKey, $this->maxConfirmAttemptsPerWindow())) {
            return [
                'ok' => false,
                'status' => 429,
                'error_code' => 'EMAIL_VERIFICATION_CONFIRM_RATE_LIMIT',
                'message' => 'Too many verification attempts. Try again later.',
                'meta' => [
                    'retry_after_seconds' => RateLimiter::availableIn($rateKey),
                ],
            ];
        }
        RateLimiter::hit($rateKey, $this->confirmWindowSeconds());

        $verification = $this->activeVerificationQuery($user, $normalizedEmail, $purpose, $emailChangeRequestId, true)
            ->latest('id')
            ->first();

        if (! $verification && $allowFallbackWithoutChangeRequestId && $emailChangeRequestId !== null) {
            $verification = $this->activeVerificationQuery($user, $normalizedEmail, $purpose, null, true)
                ->latest('id')
                ->first();
        }

        if (! $verification) {
            return [
                'ok' => false,
                'status' => 422,
                'error_code' => 'EMAIL_VERIFICATION_CODE_INVALID',
                'message' => 'Verification code is invalid.',
            ];
        }

        if ($verification->expires_at === null || now()->greaterThan($verification->expires_at)) {
            return [
                'ok' => false,
                'status' => 422,
                'error_code' => 'EMAIL_VERIFICATION_CODE_EXPIRED',
                'message' => 'Verification code has expired.',
            ];
        }

        if ($verification->attempts >= $this->maxConfirmAttemptsPerToken()) {
            return [
                'ok' => false,
                'status' => 429,
                'error_code' => 'EMAIL_VERIFICATION_CODE_ATTEMPTS_EXCEEDED',
                'message' => 'Too many attempts for this verification code.',
            ];
        }

        if (! Hash::check($normalizedCode, (string) $verification->code_hash)) {
            $verification->increment('attempts');
            $verification->refresh();

            if ($verification->attempts >= $this->maxConfirmAttemptsPerToken()) {
                return [
                    'ok' => false,
                    'status' => 429,
                    'error_code' => 'EMAIL_VERIFICATION_CODE_ATTEMPTS_EXCEEDED',
                    'message' => 'Too many attempts for this verification code.',
                ];
            }

            return [
                'ok' => false,
                'status' => 422,
                'error_code' => 'EMAIL_VERIFICATION_CODE_INVALID',
                'message' => 'Verification code is invalid.',
            ];
        }

        $verification->forceFill([
            'consumed_at' => now(),
        ])->save();

        return ['ok' => true];
    }

    private function activeVerificationQuery(
        User $user,
        string $email,
        string $purpose,
        ?int $emailChangeRequestId,
        bool $includeExpired = false,
    ) {
        $query = EmailVerification::query()
            ->where('user_id', $user->id)
            ->whereRaw('LOWER(email) = ?', [$email])
            ->where('purpose', $purpose)
            ->whereNull('consumed_at');

        if (! $includeExpired) {
            $query->where('expires_at', '>', now());
        }

        if ($emailChangeRequestId === null) {
            $query->whereNull('email_change_request_id');
        } else {
            $query->where('email_change_request_id', $emailChangeRequestId);
        }

        return $query;
    }

    private function cancelPendingEmailChanges(User $user): void
    {
        EmailChangeRequest::query()
            ->where('user_id', $user->id)
            ->whereNull('completed_at')
            ->whereNull('cancelled_at')
            ->update([
                'cancelled_at' => now(),
            ]);
    }

    private function activePendingChangeRequest(User $user): ?EmailChangeRequest
    {
        return EmailChangeRequest::query()
            ->where('user_id', $user->id)
            ->whereNull('completed_at')
            ->whereNull('cancelled_at')
            ->where('expires_at', '>', now())
            ->latest('id')
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function statusPayload(User $user): array
    {
        $email = $this->normalizeEmail((string) ($user->email ?? ''));
        $pending = $this->activePendingChangeRequest($user);
        $pendingAppliedId = ($pending && $pending->new_email_applied_at !== null)
            ? (int) $pending->id
            : null;

        return [
            'email' => $email !== '' ? $email : null,
            'verified' => (bool) $user->hasVerifiedEmail(),
            'email_verified_at' => optional($user->email_verified_at)?->toIso8601String(),
            'requires_email_verification' => (bool) $user->requires_email_verification,
            'seconds_to_resend' => $email === ''
                ? 0
                : $this->secondsToResend(
                    $user,
                    $email,
                    EmailVerification::PURPOSE_ACCOUNT_VERIFICATION,
                    $pendingAppliedId
                ),
            'pending_email_change' => $pending ? [
                'id' => (int) $pending->id,
                'current_email' => (string) $pending->current_email,
                'new_email' => (string) $pending->new_email,
                'current_email_confirmed_at' => optional($pending->current_email_confirmed_at)?->toIso8601String(),
                'new_email_applied_at' => optional($pending->new_email_applied_at)?->toIso8601String(),
                'expires_at' => optional($pending->expires_at)?->toIso8601String(),
                'seconds_to_resend_current' => $this->secondsToResend(
                    $user,
                    (string) $pending->current_email,
                    EmailVerification::PURPOSE_EMAIL_CHANGE_CURRENT,
                    (int) $pending->id
                ),
            ] : null,
        ];
    }

    private function secondsToResend(
        User $user,
        string $email,
        string $purpose,
        ?int $emailChangeRequestId = null,
    ): int {
        $verification = $this->activeVerificationQuery(
            $user,
            $this->normalizeEmail($email),
            $purpose,
            $emailChangeRequestId
        )->latest('id')->first();

        if (! $verification?->last_sent_at) {
            return 0;
        }

        $availableAt = $verification->last_sent_at->copy()->addSeconds($this->resendCooldownSeconds());
        if ($availableAt->isPast()) {
            return 0;
        }

        return now()->diffInSeconds($availableAt);
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

    private function generateFormattedCode(): string
    {
        $digits = '';
        for ($i = 0; $i < 10; $i++) {
            $digits .= (string) random_int(0, 9);
        }

        return substr($digits, 0, 5) . '-' . substr($digits, 5, 5);
    }

    private function sendRateKey(User $user, string $email, string $purpose, ?int $emailChangeRequestId = null): string
    {
        $requestIdPart = $emailChangeRequestId !== null ? (string) $emailChangeRequestId : 'none';
        return sprintf(
            'email-verification:send:%s:%d:%s:%s',
            $purpose,
            $user->id,
            sha1($email),
            $requestIdPart
        );
    }

    private function confirmRateKey(User $user, string $purpose): string
    {
        return sprintf('email-verification:confirm:%s:%d', $purpose, $user->id);
    }

    private function codeTtlMinutes(): int
    {
        return max(5, (int) config('email_verification.code_ttl_minutes', 20));
    }

    private function resendCooldownSeconds(): int
    {
        return max(10, (int) config('email_verification.resend_cooldown_seconds', 60));
    }

    private function maxSendPerHour(): int
    {
        return max(1, (int) config('email_verification.max_send_per_hour', 5));
    }

    private function maxConfirmAttemptsPerToken(): int
    {
        return max(1, (int) config('email_verification.max_confirm_attempts_per_token', 8));
    }

    private function maxConfirmAttemptsPerWindow(): int
    {
        return max(1, (int) config('email_verification.max_confirm_attempts_per_window', 10));
    }

    private function confirmWindowSeconds(): int
    {
        return max(60, (int) config('email_verification.confirm_window_seconds', 900));
    }

    private function emailChangeTtlMinutes(): int
    {
        return max(10, (int) config('email_verification.email_change_request_ttl_minutes', 60));
    }

    /**
     * @param array<string, mixed>|null $meta
     */
    private function errorResponse(
        string $message,
        string $errorCode,
        int $status,
        ?array $meta = null,
    ): JsonResponse {
        $payload = [
            'message' => $message,
            'error_code' => $errorCode,
        ];

        if ($meta !== null) {
            $payload = array_merge($payload, $meta);
        }

        return response()->json($payload, $status);
    }
}
