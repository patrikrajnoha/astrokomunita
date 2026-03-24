<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\Report;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Gate;

class ReportController extends Controller
{
    public function store(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'post_id' => ['nullable', 'integer', 'exists:posts,id', 'required_without:target_id'],
            'target_id' => ['nullable', 'integer', 'exists:posts,id', 'required_without:post_id'],
            'reason' => ['required', 'string', Rule::in(['spam', 'abuse', 'misinfo', 'other'])],
            'message' => ['nullable', 'string', 'max:500'],
            '_hp' => ['nullable', 'string', 'max:255'],
            'website' => ['nullable', 'string', 'max:255'],
        ]);

        $honeypot = trim((string) ($validated['_hp'] ?? $validated['website'] ?? ''));
        if ($honeypot !== '') {
            return response()->json([
                'message' => 'Neplatná požiadavka na nahlásenie.',
            ], 422);
        }

        $postId = (int) ($validated['post_id'] ?? $validated['target_id']);

        $targetPost = Post::query()
            ->with(['user:id,role,is_bot'])
            ->select('id', 'user_id')
            ->findOrFail($postId);

        if ($targetPost->user?->isBot()) {
            return response()->json([
                'message' => 'Prispevky botov nie je mozne nahlasovat cez moderaciu.',
            ], 422);
        }
        if ((int) $targetPost->user_id === (int) $user->id) {
            return response()->json([
                'message' => 'Nemôžete nahlásiť vlastný príspevok.',
            ], 403);
        }

        if (Gate::forUser($user)->denies('create', [Report::class, $targetPost])) {
            return response()->json([
                'message' => 'Nemôžete nahlásiť vlastný príspevok.',
            ], 403);
        }

        $exists = Report::query()
            ->where('reporter_user_id', $user->id)
            ->where('target_type', 'post')
            ->where('target_id', $postId)
            ->exists();

        if ($exists) {
            return response()->json([
                'status' => 'already_reported',
                'message' => 'Nahlasenie uz bolo odoslane.',
            ], 409);
        }

        try {
            $report = Report::create([
                'reporter_user_id' => $user->id,
                'target_id' => $postId,
                'reason' => $validated['reason'],
                'message' => $validated['message'] ?? null,
                'status' => 'open',
            ]);
        } catch (QueryException $exception) {
            if ((string) $exception->getCode() === '23000') {
                return response()->json([
                    'status' => 'already_reported',
                    'message' => 'Nahlasenie uz bolo odoslane.',
                ], 409);
            }

            throw $exception;
        }

        return response()->json($report, 201);
    }
}
