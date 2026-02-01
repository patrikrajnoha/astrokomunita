<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * GET /api/admin/dashboard?range=today|7d|30d
     */
    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'range' => 'in:today,7d,30d',
        ]);

        $range = $request->get('range', '7d');
        $timezone = 'Europe/Bratislava';
        $now = Carbon::now($timezone);

        // Calculate date range
        match ($range) {
            'today' => $startDate = $now->copy()->startOfDay(),
            '7d' => $startDate = $now->copy()->subDays(6)->startOfDay(),
            '30d' => $startDate = $now->copy()->subDays(29)->startOfDay(),
            default => $startDate = $now->copy()->subDays(6)->startOfDay(),
        };

        $endDate = $now->copy()->endOfDay();

        return response()->json([
            'totals' => $this->getTotals(),
            'range_metrics' => $this->getRangeMetrics($startDate, $endDate),
            'activity' => $this->getRecentActivity(),
            'chart_series' => $this->getChartSeries($startDate, $endDate, $range),
        ]);
    }

    private function getTotals(): array
    {
        return [
            'total_users' => DB::table('users')->count(),
            'total_posts' => DB::table('posts')->count(),
            'total_events' => DB::table('events')->whereNotNull('source_name')->whereNotNull('source_uid')->count(),
            'total_event_candidates' => DB::table('event_candidates')->count(),
            'total_reports' => DB::table('reports')->count(),
            'total_blog_posts' => DB::table('blog_posts')->count(),
        ];
    }

    private function getRangeMetrics(Carbon $startDate, Carbon $endDate): array
    {
        return [
            'new_users' => DB::table('users')->whereBetween('created_at', [$startDate, $endDate])->count(),
            'new_posts' => DB::table('posts')->whereBetween('created_at', [$startDate, $endDate])->count(),
            'new_events_published' => DB::table('events')
                ->whereNotNull('source_name')
                ->whereNotNull('source_uid')
                ->whereBetween('created_at', [$startDate, $endDate])
                ->count(),
            'new_event_candidates' => DB::table('event_candidates')->whereBetween('created_at', [$startDate, $endDate])->count(),
            'likes_count' => DB::table('post_likes')->whereBetween('created_at', [$startDate, $endDate])->count(),
            'replies_count' => DB::table('posts')->whereNotNull('parent_id')->whereBetween('created_at', [$startDate, $endDate])->count(),
        ];
    }

    private function getRecentActivity(): array
    {
        return [
            'latest_users' => DB::table('users')
                ->select('id', 'name', 'created_at')
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get()
                ->map(function ($user) {
                    return [
                        'id' => $user->id,
                        'name' => $user->name ?? '',
                        'created_at' => $user->created_at,
                    ];
                }),

            'latest_posts' => DB::table('posts')
                ->select('posts.id', 'posts.user_id', 'posts.content', 'posts.created_at', 'users.name as user_name')
                ->leftJoin('users', 'posts.user_id', '=', 'users.id')
                ->orderBy('posts.created_at', 'desc')
                ->limit(10)
                ->get()
                ->map(function ($post) {
                    $content = strip_tags($post->content ?? '');
                    $title = mb_substr($content, 0, 100, 'UTF-8');
                    if (mb_strlen($content, 'UTF-8') > 100) {
                        $title .= '...';
                    }
                    return [
                        'id' => $post->id,
                        'user_id' => $post->user_id,
                        'title' => $title,
                        'created_at' => $post->created_at,
                    ];
                }),

            'latest_event_candidates' => DB::table('event_candidates')
                ->select('id', 'title', 'status', 'source_name', 'created_at')
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get()
                ->map(function ($candidate) {
                    return [
                        'id' => $candidate->id,
                        'title' => $candidate->title ?? '',
                        'status' => $candidate->status,
                        'source' => $candidate->source_name ?? '',
                        'created_at' => $candidate->created_at,
                    ];
                }),

            'latest_events' => DB::table('events')
                ->select('id', 'title', 'start_at', 'created_at')
                ->whereNotNull('source_name')
                ->whereNotNull('source_uid')
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get()
                ->map(function ($event) {
                    return [
                        'id' => $event->id,
                        'title' => $event->title ?? '',
                        'starts_at' => $event->start_at,
                        'created_at' => $event->created_at,
                    ];
                }),
        ];
    }

    private function getChartSeries(Carbon $startDate, Carbon $endDate, string $range): array
    {
        $period = CarbonPeriod::create($startDate->copy()->startOfDay(), $endDate->copy()->endOfDay());
        $dates = [];

        foreach ($period as $date) {
            $dates[] = $date->format('Y-m-d');
        }

        return [
            'users_series' => $this->getSeriesData('users', $dates),
            'posts_series' => $this->getSeriesData('posts', $dates),
            'events_series' => $this->getSeriesData('events', $dates, true),
            'candidates_series' => $this->getSeriesData('event_candidates', $dates),
        ];
    }

    private function getSeriesData(string $table, array $dates, bool $isEvents = false): array
    {
        $query = DB::table($table)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->whereIn(DB::raw('DATE(created_at)'), $dates)
            ->groupBy('date')
            ->orderBy('date');

        if ($isEvents) {
            $query->whereNotNull('source_name')->whereNotNull('source_uid');
        }

        $data = $query->get()->keyBy('date');

        return collect($dates)->map(function ($date) use ($data) {
            return [
                'date' => $date,
                'count' => $data->get($date)?->count ?? 0,
            ];
        })->values()->toArray();
    }
}
