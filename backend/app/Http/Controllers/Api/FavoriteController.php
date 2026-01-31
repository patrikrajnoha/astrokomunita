<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Favorite;
use App\Models\Event;

class FavoriteController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        return Favorite::with('event')
            ->where('user_id', $user->id)
            ->latest()
            ->get();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'event_id' => ['required', 'exists:events,id'],
        ]);

        $favorite = Favorite::firstOrCreate([
            'user_id' => $request->user()->id,
            'event_id' => $validated['event_id'],
        ]);

        return $favorite->load('event');
    }

    public function destroy(Request $request, Event $event)
    {
        Favorite::where('user_id', $request->user()->id)
            ->where('event_id', $event->id)
            ->delete();

        return response()->noContent();
    }
}
