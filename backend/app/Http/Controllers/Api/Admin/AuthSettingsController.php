<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\Auth\EmailVerificationSettingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
            'require_email_verification' => ['required', 'boolean'],
        ]);

        return response()->json([
            'data' => $this->emailVerificationSettingService->updateRequiresEmailVerification(
                (bool) $validated['require_email_verification']
            ),
        ]);
    }
}
