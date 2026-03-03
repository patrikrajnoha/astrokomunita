<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\Auth\EmailVerificationSettingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AuthSettingsController extends Controller
{
    public function __construct(
        private readonly EmailVerificationSettingService $emailVerificationSettingService,
    ) {
    }

    public function show(): JsonResponse
    {
        return response()->json([
            'data' => $this->emailVerificationSettingService->payload(),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'require_email_verification_for_new_users' => ['nullable', 'boolean'],
            'require_email_verification' => ['nullable', 'boolean'],
        ]);

        $hasNewKey = array_key_exists('require_email_verification_for_new_users', $validated);
        $hasLegacyKey = array_key_exists('require_email_verification', $validated);

        if (! $hasNewKey && ! $hasLegacyKey) {
            throw ValidationException::withMessages([
                'require_email_verification_for_new_users' => ['The email verification setting field is required.'],
            ]);
        }

        $required = $hasNewKey
            ? (bool) $validated['require_email_verification_for_new_users']
            : (bool) $validated['require_email_verification'];

        return response()->json([
            'data' => $this->emailVerificationSettingService
                ->updateRequiresEmailVerificationForNewUsers($required),
        ]);
    }
}
