<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventEmailAlert;
use Illuminate\Http\Request;

class EventEmailAlertController extends Controller
{
    public function store(Request $request, Event $event)
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        $alert = EventEmailAlert::firstOrCreate([
            'event_id' => $event->id,
            'email' => $validated['email'],
        ], [
            'created_at' => now(),
        ]);

        return response()->json([
            'ok' => true,
            'alert_id' => $alert->id,
        ], 201);
    }
}
