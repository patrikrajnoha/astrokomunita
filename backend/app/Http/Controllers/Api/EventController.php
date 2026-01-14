<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;

class EventController extends Controller
{
    public function index()
    {
        return Event::orderBy('max_at', 'asc')->get();
    }

    public function show($id)
    {
        return Event::findOrFail($id);
    }

    public function next()
    {
        return Event::where('max_at', '>=', now())
            ->orderBy('max_at', 'asc')
            ->first();
    }
}
