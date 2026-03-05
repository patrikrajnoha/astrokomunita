<?php

namespace App\Services\Bots;

use App\Models\BotActivityLog;
use App\Models\Post;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class BotOverviewService
{
    public function __construct(
        private readonly BotRateLimiterService $rateLimiterService,
    ) {
    }

    /**
     * @return array<string,mixed>
     */
    public function buildOverview(): array
    {
        $windowStart = now()->subDay();
        $bots = User::query()
            ->where(function (Builder $query): void {
                $query
                    ->where('is_bot', true)
                    ->orWhere('role', User::ROLE_BOT);
            })
            ->orderBy('username')
            ->get(['id', 'username', 'role', 'is_bot']);

        $posts24ByUser = $this->botPostsBaseQuery()
            ->whereRaw('COALESCE(ingested_at, created_at) >= ?', [$windowStart->toDateTimeString()])
            ->selectRaw('user_id, COUNT(*) as total')
            ->groupBy('user_id')
            ->pluck('total', 'user_id');

        $lastPostByUser = $this->botPostsBaseQuery()
            ->selectRaw('user_id, MAX(COALESCE(ingested_at, created_at)) as last_seen_at')
            ->groupBy('user_id')
            ->pluck('last_seen_at', 'user_id');

        $errors24ByIdentity = BotActivityLog::query()
            ->where('created_at', '>=', $windowStart)
            ->where('outcome', 'failed')
            ->selectRaw('LOWER(COALESCE(bot_identity, "")) as identity, COUNT(*) as total')
            ->groupBy('identity')
            ->pluck('total', 'identity');

        $duplicateLogsBase = BotActivityLog::query()
            ->where('created_at', '>=', $windowStart)
            ->where(function (Builder $query): void {
                $query
                    ->where(function (Builder $dedupeQuery): void {
                        $dedupeQuery
                            ->where('action', 'ingest')
                            ->where('outcome', 'skipped_duplicate');
                    })
                    ->orWhere(function (Builder $publishSkipQuery): void {
                        $publishSkipQuery
                            ->where('action', 'publish')
                            ->where('outcome', 'skipped')
                            ->whereIn('reason', [
                                'already_linked_post',
                                'already_published_by_source_uid',
                            ]);
                    });
            });

        $duplicates24ByIdentity = (clone $duplicateLogsBase)
            ->selectRaw('LOWER(COALESCE(bot_identity, "")) as identity, COUNT(*) as total')
            ->groupBy('identity')
            ->pluck('total', 'identity');

        $lastLogByIdentity = BotActivityLog::query()
            ->selectRaw('LOWER(COALESCE(bot_identity, "")) as identity, MAX(created_at) as last_seen_at')
            ->groupBy('identity')
            ->pluck('last_seen_at', 'identity');

        $identityMap = $this->identityByUsername();

        $botRows = $bots->map(function (User $botUser) use (
            $posts24ByUser,
            $lastPostByUser,
            $errors24ByIdentity,
            $duplicates24ByIdentity,
            $lastLogByIdentity,
            $identityMap
        ): array {
            $identity = $this->resolveIdentityForUser($botUser, $identityMap);

            $posts24 = (int) ($posts24ByUser[(string) $botUser->id] ?? $posts24ByUser[$botUser->id] ?? 0);
            $errors24 = (int) ($errors24ByIdentity[$identity] ?? 0);
            $duplicates24 = (int) ($duplicates24ByIdentity[$identity] ?? 0);

            $lastPostAt = $lastPostByUser[(string) $botUser->id] ?? $lastPostByUser[$botUser->id] ?? null;
            $lastLogAt = $lastLogByIdentity[$identity] ?? null;
            $lastActivityAt = $this->maxIsoDatetime($lastPostAt, $lastLogAt);

            $rateLimitState = $this->rateLimiterService->resolvePublishState($identity);

            return [
                'id' => $botUser->id,
                'username' => (string) $botUser->username,
                'role' => (string) ($botUser->role ?: User::ROLE_BOT),
                'bot_identity' => $identity,
                'last_activity_at' => $lastActivityAt,
                'posts_24h' => $posts24,
                'duplicates_24h' => $duplicates24,
                'errors_24h' => $errors24,
                'rate_limit_state' => [
                    'limited' => (bool) ($rateLimitState['limited'] ?? false),
                    'retry_after_sec' => (int) ($rateLimitState['retry_after_sec'] ?? 0),
                    'remaining_attempts' => (int) ($rateLimitState['remaining_attempts'] ?? 0),
                    'max_attempts' => (int) ($rateLimitState['max_attempts'] ?? 0),
                    'window_sec' => (int) ($rateLimitState['window_sec'] ?? 0),
                ],
            ];
        })->values();

        return [
            'window_hours' => 24,
            'generated_at' => now()->toIso8601String(),
            'bots' => $botRows,
            'overall' => [
                'posts_24h_total' => (int) $botRows->sum('posts_24h'),
                'duplicates_24h' => (int) (clone $duplicateLogsBase)->count(),
                'failures_24h' => (int) BotActivityLog::query()
                    ->where('created_at', '>=', $windowStart)
                    ->where('outcome', 'failed')
                    ->count(),
            ],
        ];
    }

    private function botPostsBaseQuery(): Builder
    {
        return Post::query()
            ->whereNotNull('user_id')
            ->where(function (Builder $query): void {
                $query
                    ->whereNotNull('bot_item_id')
                    ->orWhere('source_name', 'like', 'bot_%');
            });
    }

    /**
     * @return array<string,string>
     */
    private function identityByUsername(): array
    {
        $rows = (array) config('bots.identities', []);
        $map = [];

        foreach ($rows as $identity => $definition) {
            $username = strtolower(trim((string) data_get($definition, 'username')));
            if ($username !== '') {
                $map[$username] = strtolower(trim((string) $identity));
            }
        }

        return $map;
    }

    /**
     * @param array<string,string> $identityByUsername
     */
    private function resolveIdentityForUser(User $botUser, array $identityByUsername): string
    {
        $username = strtolower(trim((string) $botUser->username));
        if ($username !== '' && isset($identityByUsername[$username])) {
            return $identityByUsername[$username];
        }

        if (str_contains($username, 'stela')) {
            return 'stela';
        }

        if (str_contains($username, 'kozmo')) {
            return 'kozmo';
        }

        return 'unknown';
    }

    private function maxIsoDatetime(mixed $first, mixed $second): ?string
    {
        $firstTs = $this->toTimestamp($first);
        $secondTs = $this->toTimestamp($second);

        if ($firstTs === null && $secondTs === null) {
            return null;
        }

        $max = max((int) ($firstTs ?? 0), (int) ($secondTs ?? 0));
        if ($max <= 0) {
            return null;
        }

        return Carbon::createFromTimestamp($max)->toIso8601String();
    }

    private function toTimestamp(mixed $value): ?int
    {
        if ($value instanceof Carbon) {
            return $value->timestamp;
        }
        if (!is_string($value) && !is_numeric($value)) {
            return null;
        }

        $ts = strtotime((string) $value);
        if ($ts === false || $ts <= 0) {
            return null;
        }

        return $ts;
    }
}
