<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateUserNotificationPreferencesRequest;
use App\Models\UserNotificationPreference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class UserNotificationPreferenceController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        try {
            $preferences = UserNotificationPreference::query()->firstOrNew(
                ['user_id' => $request->user()->id],
                UserNotificationPreference::defaults()
            );

            return response()->json($this->payload($preferences));
        } catch (Throwable $exception) {
            return $this->unavailableResponse($request->user()->id, 'show', $exception);
        }
    }

    public function update(UpdateUserNotificationPreferencesRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            $preferences = UserNotificationPreference::query()->updateOrCreate(
                ['user_id' => $request->user()->id],
                [
                    'iss_alerts' => (bool) $validated['iss_alerts'],
                    'good_conditions_alerts' => (bool) $validated['good_conditions_alerts'],
                ]
            );

            return response()->json($this->payload($preferences));
        } catch (Throwable $exception) {
            return $this->unavailableResponse($request->user()->id, 'update', $exception);
        }
    }

    /**
     * @return array{iss_alerts:bool,good_conditions_alerts:bool}
     */
    private function payload(UserNotificationPreference $preferences): array
    {
        return [
            'iss_alerts' => (bool) $preferences->iss_alerts,
            'good_conditions_alerts' => (bool) $preferences->good_conditions_alerts,
        ];
    }

    private function unavailableResponse(int $userId, string $action, Throwable $exception): JsonResponse
    {
        Log::warning('User notification preferences unavailable.', [
            'action' => $action,
            'user_id' => $userId,
            'exception' => $exception::class,
            'message' => $exception->getMessage(),
        ]);

        return response()->json([
            ...UserNotificationPreference::defaults(),
            'reason' => 'unavailable',
        ]);
    }
}
