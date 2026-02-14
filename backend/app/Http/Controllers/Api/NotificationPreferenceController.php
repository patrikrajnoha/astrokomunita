<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateNotificationPreferencesRequest;
use App\Models\NotificationPreference;
use Illuminate\Http\Request;

class NotificationPreferenceController extends Controller
{
    public function show(Request $request)
    {
        $preferences = NotificationPreference::ensureForUser($request->user()->id);

        return response()->json([
            'in_app' => $preferences->inApp(),
            'email_enabled' => (bool) $preferences->email_enabled,
            'email' => $preferences->email(),
        ]);
    }

    public function update(UpdateNotificationPreferencesRequest $request)
    {
        $validated = $request->validated();
        $userId = $request->user()->id;

        $preferences = NotificationPreference::ensureForUser($userId);
        $preferences->forceFill([
            'in_app_json' => $validated['in_app'],
            'email_enabled' => (bool) $validated['email_enabled'],
            'email_json' => $validated['email'] ?? null,
        ])->save();

        return response()->json([
            'in_app' => $preferences->inApp(),
            'email_enabled' => (bool) $preferences->email_enabled,
            'email' => $preferences->email(),
        ]);
    }
}
