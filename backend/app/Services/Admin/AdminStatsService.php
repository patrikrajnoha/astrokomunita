<?php

namespace App\Services\Admin;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AdminStatsService
{
    /**
     * @return array{
     *     kpi: array<string,int>,
     *     demographics: array{by_role: array<string,int>, by_region: array<string,int>},
     *     trend: array{range_days:int, points: array<int,array{date:string,new_users:int,new_posts:int,new_events:int}>},
     *     generated_at: string
     * }
     */
    public function build(int $rangeDays = 30): array
    {
        $rangeDays = max(1, $rangeDays);
        $startDate = Carbon::today()->subDays($rangeDays - 1);
        $endDate = Carbon::today();
        $dateKeys = [];

        for ($cursor = $startDate->copy(); $cursor->lte($endDate); $cursor->addDay()) {
            $dateKeys[] = $cursor->toDateString();
        }

        $activityField = Schema::hasColumn('users', 'last_login_at') ? 'last_login_at' : 'created_at';
        $usersActiveThreshold = Carbon::now()->subDays(30);
        $moderationStatuses = ['flagged', 'blocked', 'ok'];

        $usersTotal = (int) DB::table('users')->count();
        $usersActive30d = (int) DB::table('users')
            ->whereNotNull($activityField)
            ->where($activityField, '>=', $usersActiveThreshold)
            ->count();
        $postsTotal = (int) DB::table('posts')->count();
        $eventsTotal = (int) DB::table('events')->count();
        $postsModeratedTotal = Schema::hasColumn('posts', 'moderation_status')
            ? (int) DB::table('posts')->whereIn('moderation_status', $moderationStatuses)->count()
            : 0;

        $roleRows = DB::table('users')
            ->selectRaw(
                "CASE
                    WHEN is_bot = 1 THEN 'bot'
                    WHEN role = 'admin' OR is_admin = 1 THEN 'admin'
                    ELSE 'user'
                END as role_bucket"
            )
            ->selectRaw('COUNT(*) as total')
            ->groupBy('role_bucket')
            ->get();
        $byRole = [
            'user' => 0,
            'admin' => 0,
            'bot' => 0,
        ];
        foreach ($roleRows as $row) {
            $bucket = (string) $row->role_bucket;
            if (array_key_exists($bucket, $byRole)) {
                $byRole[$bucket] = (int) $row->total;
            }
        }

        $regionRows = DB::table('users')
            ->selectRaw(
                "CASE
                    WHEN location IS NULL OR TRIM(location) = '' THEN 'unknown'
                    WHEN LOWER(location) LIKE '%sk' OR LOWER(location) LIKE '%slovakia%' OR LOWER(location) LIKE '%slovensko%' THEN 'sk'
                    WHEN LOWER(location) LIKE '%cz' OR LOWER(location) LIKE '%czechia%' OR LOWER(location) LIKE '%czech republic%' THEN 'cz'
                    ELSE 'other'
                END as region_bucket"
            )
            ->selectRaw('COUNT(*) as total')
            ->groupBy('region_bucket')
            ->get();
        $byRegion = [
            'unknown' => 0,
            'sk' => 0,
            'cz' => 0,
            'other' => 0,
        ];
        foreach ($regionRows as $row) {
            $bucket = (string) $row->region_bucket;
            if (array_key_exists($bucket, $byRegion)) {
                $byRegion[$bucket] = (int) $row->total;
            }
        }

        $usersTrend = DB::table('users')
            ->selectRaw('DATE(created_at) as date_key, COUNT(*) as total')
            ->whereDate('created_at', '>=', $startDate->toDateString())
            ->whereDate('created_at', '<=', $endDate->toDateString())
            ->groupBy('date_key')
            ->pluck('total', 'date_key');
        $postsTrend = DB::table('posts')
            ->selectRaw('DATE(created_at) as date_key, COUNT(*) as total')
            ->whereDate('created_at', '>=', $startDate->toDateString())
            ->whereDate('created_at', '<=', $endDate->toDateString())
            ->groupBy('date_key')
            ->pluck('total', 'date_key');
        $eventsTrend = DB::table('events')
            ->selectRaw('DATE(created_at) as date_key, COUNT(*) as total')
            ->whereDate('created_at', '>=', $startDate->toDateString())
            ->whereDate('created_at', '<=', $endDate->toDateString())
            ->groupBy('date_key')
            ->pluck('total', 'date_key');

        $points = [];
        foreach ($dateKeys as $dateKey) {
            $points[] = [
                'date' => $dateKey,
                'new_users' => (int) ($usersTrend[$dateKey] ?? 0),
                'new_posts' => (int) ($postsTrend[$dateKey] ?? 0),
                'new_events' => (int) ($eventsTrend[$dateKey] ?? 0),
            ];
        }

        return [
            'kpi' => [
                'users_total' => $usersTotal,
                'users_active_30d' => $usersActive30d,
                'posts_total' => $postsTotal,
                'events_total' => $eventsTotal,
                'posts_moderated_total' => $postsModeratedTotal,
            ],
            'demographics' => [
                'by_role' => $byRole,
                'by_region' => $byRegion,
            ],
            'trend' => [
                'range_days' => $rangeDays,
                'points' => $points,
            ],
            'generated_at' => now()->toIso8601String(),
        ];
    }
}

