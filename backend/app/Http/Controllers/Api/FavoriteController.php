<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Favorite;
use App\Models\Event;

class FavoriteController extends Controller
{
    public function index()
    {
        return Favorite::with('event')
            ->latest()
            ->get();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'event_id' => ['required', 'exists:events,id'],
        ]);

        $favorite = Favorite::firstOrCreate([
            'user_id' => null, // neskôr: auth()->id()
            'event_id' => $validated['event_id'],
        ]);

        // vráť aj event (aby frontend hneď videl title/max_at/...)
        return $favorite->load('event');
    }

    public function destroy(Event $event)
    {
        // teraz: user_id je null (MVP)
        // neskôr: pridať ->where('user_id', auth()->id())
        Favorite::where('user_id', null)
            ->where('event_id', $event->id)
            ->delete();

        return response()->noContent();
    }
}
