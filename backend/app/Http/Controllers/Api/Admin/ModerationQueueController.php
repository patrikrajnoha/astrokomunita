<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ModerationActionRequest;
use App\Models\ModerationLog;
use App\Models\Post;
use Illuminate\Http\Request;

class ModerationQueueController extends Controller
{
    public function index(Request $request)
    {
        $perPage = max(1, min((int) $request->query('per_page', 20), 50));
        $status = (string) $request->query('status', 'pending');

        $query = Post::query()
            ->with(['user:id,name,username,avatar_path'])
            ->orderByDesc('created_at');

        if (in_array($status, ['pending', 'flagged', 'blocked', 'ok'], true)) {
            $query->where('moderation_status', $status);
        } elseif ($status === 'reviewed') {
            $query->whereIn('id', function ($subQuery) {
                $subQuery->select('entity_id')
                    ->from('moderation_logs')
                    ->where('entity_type', 'post')
                    ->whereNotNull('reviewed_by_admin_id');
            });
        }

        if ($request->filled('search')) {
            $search = '%' . trim((string) $request->query('search')) . '%';
            $query->where('content', 'like', $search);
        }

        $items = $query->paginate($perPage)->through(function (Post $post) {
            $latestLog = ModerationLog::query()
                ->where('entity_type', 'post')
                ->where('entity_id', $post->id)
                ->latest('id')
                ->first();

            return [
                'id' => $post->id,
                'entity_type' => 'post',
                'snippet' => mb_substr((string) $post->content, 0, 140),
                'attachment_url' => $post->attachment_url,
                'attachment_mime' => $post->attachment_mime,
                'moderation_status' => $post->moderation_status,
                'moderation_summary' => $post->moderation_summary,
                'created_at' => $post->created_at,
                'user' => $post->user,
                'latest_log' => $latestLog,
            ];
        });

        return response()->json($items);
    }

    public function show(Post $post)
    {
        $logs = ModerationLog::query()
            ->with('reviewer:id,name,username')
            ->where(function ($query) use ($post) {
                $query->where('entity_type', 'post')->where('entity_id', $post->id)
                    ->orWhere(function ($mediaQuery) use ($post) {
                        $mediaQuery->where('entity_type', 'media')->where('entity_id', $post->id);
                    });
            })
            ->latest('id')
            ->get();

        return response()->json([
            'post' => $post->load('user:id,name,username,avatar_path'),
            'logs' => $logs,
        ]);
    }

    public function action(ModerationActionRequest $request, Post $post)
    {
        $action = $request->validated('action');
        $note = $request->validated('note');

        if ($action === 'approve') {
            $post->forceFill([
                'moderation_status' => 'ok',
                'is_hidden' => false,
                'hidden_reason' => null,
                'hidden_at' => null,
                'attachment_moderation_status' => $post->attachment_path ? 'ok' : null,
                'attachment_is_blurred' => false,
                'attachment_hidden_at' => null,
            ])->save();
        }

        if ($action === 'reject') {
            $post->forceFill([
                'moderation_status' => 'blocked',
                'is_hidden' => true,
                'hidden_reason' => 'blocked_by_admin_review',
                'hidden_at' => now(),
                'attachment_moderation_status' => $post->attachment_path ? 'blocked' : null,
                'attachment_is_blurred' => $post->attachment_path ? true : false,
                'attachment_hidden_at' => $post->attachment_path ? now() : null,
            ])->save();
        }

        ModerationLog::query()
            ->whereIn('entity_type', ['post', 'media'])
            ->where('entity_id', $post->id)
            ->whereNull('reviewed_by_admin_id')
            ->update([
                'reviewed_by_admin_id' => $request->user()->id,
                'admin_action' => $action,
                'admin_note' => $note,
            ]);

        return response()->json([
            'post' => $post->fresh(),
        ]);
    }
}
