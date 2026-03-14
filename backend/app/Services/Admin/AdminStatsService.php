<?php

namespace App\Services\Admin;

use App\Services\Location\IpLocationService;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class AdminStatsService
{
    public function __construct(
        private readonly IpLocationService $ipLocationService,
    ) {
    }

    /**
     * @return array{
     *     kpi: array<string,int>,
     *     demographics: array{
     *         by_role: array<string,int>,
     *         by_region: array<string,int>,
     *         by_region_active_ip_30d: array<string,int>
     *     },
     *     queues: array{event_candidates_pending:int, moderation_pending:int, moderation_flagged:int},
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
        $eventCandidatesPending = Schema::hasTable('event_candidates') && Schema::hasColumn('event_candidates', 'status')
            ? (int) DB::table('event_candidates')->where('status', 'pending')->count()
            : 0;
        $moderationPending = Schema::hasColumn('posts', 'moderation_status')
            ? (int) DB::table('posts')->where('moderation_status', 'pending')->count()
            : 0;
        $moderationFlagged = Schema::hasColumn('posts', 'moderation_status')
            ? (int) DB::table('posts')->where('moderation_status', 'flagged')->count()
            : 0;

        $roleRows = DB::table('users')
            ->selectRaw(
                "CASE
                    WHEN is_bot = 1 OR role = 'bot' THEN 'bot'
                    WHEN role = 'admin' OR is_admin = 1 THEN 'admin'
                    WHEN role = 'editor' THEN 'editor'
                    ELSE 'user'
                END as role_bucket"
            )
            ->selectRaw('COUNT(*) as total')
            ->groupBy('role_bucket')
            ->get();
        $byRole = [
            'user' => 0,
            'admin' => 0,
            'editor' => 0,
            'bot' => 0,
        ];
        foreach ($roleRows as $row) {
            $bucket = (string) $row->role_bucket;
            if (array_key_exists($bucket, $byRole)) {
                $byRole[$bucket] = (int) $row->total;
            }
        }

        $byRegion = $this->buildProfileRegionBreakdown();
        $byRegionActiveIp30d = $this->buildActiveIpRegionBreakdown($activityField, $usersActiveThreshold);

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
                'by_region_active_ip_30d' => $byRegionActiveIp30d,
            ],
            'queues' => [
                'event_candidates_pending' => $eventCandidatesPending,
                'moderation_pending' => $moderationPending,
                'moderation_flagged' => $moderationFlagged,
            ],
            'trend' => [
                'range_days' => $rangeDays,
                'points' => $points,
            ],
            'generated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * @return array{unknown:int,sk:int,cz:int,other:int}
     */
    private function buildProfileRegionBreakdown(): array
    {
        $buckets = [
            'unknown' => 0,
            'sk' => 0,
            'cz' => 0,
            'other' => 0,
        ];

        $columns = ['location'];
        if (Schema::hasColumn('users', 'location_label')) {
            $columns[] = 'location_label';
        }
        if (Schema::hasColumn('users', 'timezone')) {
            $columns[] = 'timezone';
        }
        if (Schema::hasColumn('users', 'latitude')) {
            $columns[] = 'latitude';
        }
        if (Schema::hasColumn('users', 'longitude')) {
            $columns[] = 'longitude';
        }

        $rows = $this->applyHumanUsersOnly(DB::table('users'))
            ->select($columns)
            ->get();
        foreach ($rows as $row) {
            $bucket = $this->resolveProfileRegionBucket($row);
            $buckets[$bucket] = (int) ($buckets[$bucket] ?? 0) + 1;
        }

        return $buckets;
    }

    private function resolveProfileRegionBucket(object $row): string
    {
        $locationLabel = trim((string) ($row->location_label ?? ''));
        $location = trim((string) ($row->location ?? ''));
        $timezone = trim((string) ($row->timezone ?? ''));
        $hasAnySignal = $locationLabel !== '' || $location !== '' || $timezone !== '';

        $bucket = $this->resolveCountryBucketFromText($locationLabel);
        if ($bucket !== null) {
            return $bucket;
        }

        $bucket = $this->resolveCountryBucketFromText($location);
        if ($bucket !== null) {
            return $bucket;
        }

        $bucket = $this->resolveTimezoneBucket($timezone);
        if ($bucket !== null) {
            return $bucket;
        }

        $bucket = $this->resolveCoordinatesBucket($row->latitude ?? null, $row->longitude ?? null);
        if ($bucket !== null) {
            return $bucket;
        }

        return $hasAnySignal ? 'other' : 'unknown';
    }

    private function resolveCountryBucketFromText(?string $value): ?string
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        $ascii = strtolower((string) Str::of($raw)->ascii());
        $normalized = preg_replace('/[^a-z0-9]+/', ' ', $ascii);
        $normalized = is_string($normalized) ? trim(preg_replace('/\s+/', ' ', $normalized) ?? '') : '';
        if ($normalized === '') {
            return null;
        }
        $haystack = ' ' . $normalized . ' ';

        $isSlovakia = preg_match('/(?:^|\s)(sk|svk)(?:\s|$)/', $haystack) === 1
            || str_contains($haystack, ' slovakia ')
            || str_contains($haystack, ' slovensko ')
            || str_contains($haystack, ' slovenska republika ')
            || str_contains($haystack, ' slovak republic ');
        if ($isSlovakia) {
            return 'sk';
        }

        $isCzech = preg_match('/(?:^|\s)(cz|cze|cs)(?:\s|$)/', $haystack) === 1
            || str_contains($haystack, ' czechia ')
            || str_contains($haystack, ' czech republic ')
            || str_contains($haystack, ' cesko ')
            || str_contains($haystack, ' ceska republika ');
        if ($isCzech) {
            return 'cz';
        }

        return null;
    }

    private function resolveTimezoneBucket(?string $timezone): ?string
    {
        $normalized = strtolower(trim((string) $timezone));
        if ($normalized === '') {
            return null;
        }
        if ($normalized === 'europe/bratislava') {
            return 'sk';
        }
        if ($normalized === 'europe/prague') {
            return 'cz';
        }

        return null;
    }

    private function resolveCoordinatesBucket(mixed $latitude, mixed $longitude): ?string
    {
        if (! is_numeric($latitude) || ! is_numeric($longitude)) {
            return null;
        }

        $lat = (float) $latitude;
        $lon = (float) $longitude;

        $inSk = $lat >= 47.65 && $lat <= 49.70 && $lon >= 16.75 && $lon <= 22.70;
        $inCz = $lat >= 48.50 && $lat <= 51.20 && $lon >= 12.00 && $lon <= 18.90;

        if ($inSk && ! $inCz) {
            return 'sk';
        }
        if ($inCz && ! $inSk) {
            return 'cz';
        }

        return null;
    }

    /**
     * @return array{unknown:int,sk:int,cz:int,other:int}
     */
    private function buildActiveIpRegionBreakdown(string $activityField, Carbon $usersActiveThreshold): array
    {
        $buckets = [
            'unknown' => 0,
            'sk' => 0,
            'cz' => 0,
            'other' => 0,
        ];

        $activeUserIds = $this->applyHumanUsersOnly(DB::table('users'))
            ->whereNotNull($activityField)
            ->where($activityField, '>=', $usersActiveThreshold)
            ->pluck('id')
            ->map(static fn ($value): int => (int) $value)
            ->all();

        if ($activeUserIds === []) {
            return $buckets;
        }

        if (! (bool) config('admin.stats_ip_region_enabled', true)) {
            $buckets['unknown'] = count($activeUserIds);
            return $buckets;
        }

        if (
            ! Schema::hasTable('sessions')
            || ! Schema::hasColumn('sessions', 'user_id')
            || ! Schema::hasColumn('sessions', 'ip_address')
            || ! Schema::hasColumn('sessions', 'last_activity')
        ) {
            $buckets['unknown'] = count($activeUserIds);
            return $buckets;
        }

        $activeSinceUnix = $usersActiveThreshold->getTimestamp();
        $sessionRows = DB::table('sessions')
            ->select(['user_id', 'ip_address', 'last_activity'])
            ->whereIn('user_id', $activeUserIds)
            ->whereNotNull('ip_address')
            ->where('ip_address', '<>', '')
            ->where('last_activity', '>=', $activeSinceUnix)
            ->orderByDesc('last_activity')
            ->get();

        $latestIpByUserId = [];
        foreach ($sessionRows as $row) {
            $userId = (int) ($row->user_id ?? 0);
            if ($userId <= 0 || array_key_exists($userId, $latestIpByUserId)) {
                continue;
            }

            $latestIpByUserId[$userId] = (string) $row->ip_address;
        }

        $remainingLookups = max(0, (int) config('admin.stats_ip_region_lookup_max_per_build', 64));

        foreach ($activeUserIds as $userId) {
            $ip = $latestIpByUserId[$userId] ?? null;
            $bucket = $this->resolveIpRegionBucket($ip, $remainingLookups);
            $buckets[$bucket] = (int) ($buckets[$bucket] ?? 0) + 1;
        }

        return $buckets;
    }

    private function resolveIpRegionBucket(?string $ip, int &$remainingLookups): string
    {
        $normalizedIp = trim((string) $ip);
        if ($normalizedIp === '') {
            return 'unknown';
        }

        $isPublicIp = filter_var(
            $normalizedIp,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) !== false;
        if (! $isPublicIp) {
            return 'unknown';
        }

        $cacheKey = $this->ipRegionCacheKey($normalizedIp);
        $cachedBucket = $this->normalizeIpRegionBucket(Cache::get($cacheKey));
        if ($cachedBucket !== null) {
            return $cachedBucket;
        }

        if ($remainingLookups <= 0) {
            return 'unknown';
        }
        $remainingLookups--;

        $ttlSeconds = max(300, (int) config('admin.stats_ip_region_cache_ttl_seconds', 86_400));
        $payload = $this->ipLocationService->lookup($normalizedIp);
        $bucket = $this->resolveCountryBucket(is_array($payload) ? $payload : []);
        Cache::put($cacheKey, $bucket, now()->addSeconds($ttlSeconds));

        return $bucket;
    }

    private function ipRegionCacheKey(string $normalizedIp): string
    {
        return 'admin:stats:ip-region:' . hash('sha256', $normalizedIp);
    }

    private function normalizeIpRegionBucket(mixed $value): ?string
    {
        $normalized = strtolower(trim((string) $value));

        return in_array($normalized, ['unknown', 'sk', 'cz', 'other'], true)
            ? $normalized
            : null;
    }

    /**
     * @param array<string,mixed> $payload
     */
    private function resolveCountryBucket(array $payload): string
    {
        $countryCode = strtolower(trim((string) ($payload['country_code'] ?? '')));
        if ($countryCode === 'sk') {
            return 'sk';
        }
        if (in_array($countryCode, ['cz', 'cs'], true)) {
            return 'cz';
        }

        $country = strtolower(trim((string) ($payload['country'] ?? '')));
        if ($country === '' || $country === 'unknown') {
            return 'unknown';
        }

        $countryAscii = strtolower((string) Str::of($country)->ascii());
        $isSlovakia = preg_match('/\b(slovakia|slovensko)\b/u', $country) === 1
            || preg_match('/\b(slovakia|slovensko)\b/u', $countryAscii) === 1;
        if ($isSlovakia) {
            return 'sk';
        }

        $isCzech = preg_match('/\b(czechia|czech republic|cesko)\b/u', $country) === 1
            || preg_match('/\b(czechia|czech republic|cesko)\b/u', $countryAscii) === 1;
        if ($isCzech) {
            return 'cz';
        }

        return 'other';
    }

    private function applyHumanUsersOnly(Builder $query): Builder
    {
        if (Schema::hasColumn('users', 'is_bot')) {
            $query->where(function (Builder $inner): void {
                $inner->whereNull('is_bot')->orWhere('is_bot', false);
            });
        }

        if (Schema::hasColumn('users', 'role')) {
            $query->where(function (Builder $inner): void {
                $inner->whereNull('role')->orWhere('role', '!=', 'bot');
            });
        }

        return $query;
    }
}
