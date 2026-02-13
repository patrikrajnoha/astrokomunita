<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Report;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ReportQueueController extends Controller
{
    public function index(Request $request)
    {
        $perPage = (int) $request->query('per_page', 20);
        if ($perPage < 1) {
            $perPage = 1;
        }
        if ($perPage > 50) {
            $perPage = 50;
        }

        $status = $request->query('status', 'open');

        $query = Report::query()
            ->with([
                'reporter:id,name',
                'target:id,content,user_id',
                'target.user:id,name',
            ])
            ->orderByDesc('created_at');

        if ($status) {
            $query->where('status', $status);
        }

        return response()->json($query->paginate($perPage));
    }

    public function dismiss(Request $request, Report $report)
    {
        $this->authorizeReview($request, $report);

        $report->status = 'dismissed';
        $report->reviewed_by = $request->user()->id;
        $report->save();

        return $this->withRelations($report);
    }

    public function hide(Request $request, Report $report)
    {
        $this->authorizeReview($request, $report);

        $post = $report->target;
        if ($post) {
            $post->is_hidden = true;
            $post->hidden_reason = $report->reason;
            $post->save();
        }

        $report->status = 'action_taken';
        $report->admin_action = 'hidden';
        $report->reviewed_by = $request->user()->id;
        $report->save();

        return $this->withRelations($report);
    }

    public function delete(Request $request, Report $report)
    {
        $this->authorizeReview($request, $report);

        $post = $report->target;
        if ($post) {
            $post->delete();
        }

        $report->status = 'action_taken';
        $report->admin_action = 'deleted';
        $report->reviewed_by = $request->user()->id;
        $report->save();

        return $this->withRelations($report);
    }

    public function warn(Request $request, Report $report)
    {
        $this->authorizeReview($request, $report);

        $post = $report->target;
        if ($post && $post->user) {
            $post->user->increment('warning_count');
        }

        $report->status = 'action_taken';
        $report->admin_action = 'warned';
        $report->reviewed_by = $request->user()->id;
        $report->save();

        return $this->withRelations($report);
    }

    public function ban(Request $request, Report $report)
    {
        $this->authorizeReview($request, $report);

        $post = $report->target;
        if ($post && $post->user) {
            $post->user->is_banned = true;
            $post->user->save();
        }

        $report->status = 'action_taken';
        $report->admin_action = 'banned';
        $report->reviewed_by = $request->user()->id;
        $report->save();

        return $this->withRelations($report);
    }

    private function withRelations(Report $report)
    {
        return response()->json($report->load([
            'reporter:id,name',
            'target:id,content,user_id',
            'target.user:id,name',
        ]));
    }

    private function authorizeReview(Request $request, Report $report): void
    {
        if (Gate::forUser($request->user())->denies('review', $report)) {
            abort(403, 'Forbidden');
        }
    }
}
