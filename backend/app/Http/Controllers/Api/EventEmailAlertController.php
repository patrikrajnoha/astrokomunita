<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use Illuminate\Http\Request;

class EventEmailAlertController extends Controller
{
    public function store(Request $request, Event $event)
    {
        return response()->json([
            'message' => 'Email alerts are currently disabled.',
            'status' => 'disabled',
        ], 410);
    }
}
