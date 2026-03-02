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
        if ($user->isAdmin()) {
            $timezone = (string) config('events.timezone', config('app.timezone', 'UTC'));
            return response()->json([
                'mode' => 'disabled',
                'events' => [],
                'should_show' => false,
                'reason' => 'admin_disabled',
                'force_version' => 0,
                'month_key' => now($timezone)->format('Y-m'),
                'selection_mode' => 'disabled',
                'fallback_reason' => 'admin_disabled',
                'items' => [],
                'calendar' => [
                    'bundle_ics_url' => null,
                ],
                'meta' => [
                    'max_items' => 0,
                    'max_rows' => 0,
                ],
                'generated_at' => now()->toIso8601String(),
            ]);
        }

        return response()->json($this->popupService->payloadForUser($user));
    }

    public function seen(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 401);
        if ($user->isAdmin()) {
            return response()->json([
                'ok' => true,
            ]);
        }

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
