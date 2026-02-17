<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MarkYourCalendarPopupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MarkYourCalendarPopupController extends Controller
{
    public function __construct(
        private readonly MarkYourCalendarPopupService $popupService,
    ) {
    }

    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 401);

        return response()->json($this->popupService->payloadForUser($user));
    }

    public function seen(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 401);

        $validated = $request->validate([
            'force_version' => ['nullable', 'integer', 'min:0'],
            'month_key' => ['nullable', 'string', 'regex:/^\d{4}-\d{2}$/'],
        ]);

        $this->popupService->acknowledgeSeen(
            $user,
            (int) ($validated['force_version'] ?? 0)
        );

        return response()->json([
            'ok' => true,
        ]);
    }
}

