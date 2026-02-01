<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\Report;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReportController extends Controller
{
    public function store(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'target_id' => ['required', 'integer', 'exists:posts,id'],
            'reason' => ['required', 'string', Rule::in(['spam', 'abuse', 'misinfo', 'other'])],
            'message' => ['nullable', 'string', 'max:500'],
        ]);

        $exists = Report::query()
            ->where('reporter_user_id', $user->id)
            ->where('target_type', 'post')
            ->where('target_id', $validated['target_id'])
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'Report already submitted.',
            ], 409);
        }

        $report = Report::create([
            'reporter_user_id' => $user->id,
            'target_type' => 'post',
            'target_id' => $validated['target_id'],
            'reason' => $validated['reason'],
            'message' => $validated['message'] ?? null,
            'status' => 'open',
        ]);

        return response()->json($report, 201);
    }
}
