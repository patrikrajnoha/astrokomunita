<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ModerationLog;
use App\Models\Post;
use App\Models\Report;
use App\Services\Moderation\ModerationClient;
use App\Services\Moderation\ModerationClientException;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class ModerationHubController extends Controller
{
    public function overview(ModerationClient $moderationClient)
    {
        return response()->json([
            'service' => $this->resolveServiceStatus($moderationClient),
            'counts' => [
                'queue_pending' => Post::query()->where('moderation_status', 'pending')->count(),
                'queue_flagged' => Post::query()->where('moderation_status', 'flagged')->count(),
                'queue_blocked' => Post::query()->where('moderation_status', 'blocked')->count(),
                'queue_reviewed' => ModerationLog::query()
                    ->where('entity_type', 'post')
                    ->whereNotNull('reviewed_by_admin_id')
                    ->distinct()
                    ->count('entity_id'),
                'reports_open' => Report::query()->where('status', 'open')->count(),
                'reports_closed' => Report::query()->where('status', '!=', 'open')->count(),
            ],
        ]);
    }

    public function reviewFeed(Request $request)
    {
        $limit = max(1, min((int) $request->query('limit', 50), 100));
        $mode = (string) $request->query('mode', 'actionable');

        $reports = $this->reportFeedQuery($mode)
            ->limit($limit)
            ->get()
            ->map(fn (Report $report) => $this->normalizeReportItem($report));

        $queueItems = $this->queueFeedQuery($mode)
            ->limit($limit)
            ->get()
            ->map(fn (Post $post) => $this->normalizeQueueItem($post, $mode));

        $items = $reports
            ->concat($queueItems)
            ->sortByDesc(fn (array $item) => strtotime((string) ($item['created_at'] ?? '')) ?: 0)
            ->take($limit)
            ->values();

        return response()->json($items);
    }

    private function resolveServiceStatus(ModerationClient $moderationClient): array
    {
        $checkedAt = now()->toIso8601String();
        $baseUrl = trim((string) config('moderation.base_url', ''));

        if (!(bool) config('moderation.enabled', true) || $baseUrl === '') {
            return [
                'status' => 'unknown',
                'last_check_at' => $checkedAt,
            ];
        }

        try {
            $moderationClient->health();

            return [
                'status' => 'running',
                'last_check_at' => $checkedAt,
            ];
        } catch (ModerationClientException) {
            return [
                'status' => 'down',
                'last_check_at' => $checkedAt,
            ];
        }
    }

    private function normalizeReportItem(Report $report): array
    {
        $target = $report->target;
        $targetType = $this->normalizeTargetType((string) ($report->target_type ?: 'post'));
        $snippet = $this->trimSnippet((string) ($target?->content ?? ''));
        $author = (string) ($target?->user?->name ?: $target?->user?->username ?: '');

        return [
            'kind' => 'report',
            'id' => (string) $report->id,
            'created_at' => optional($report->created_at)->toIso8601String(),
            'label' => sprintf('%s #%d', ucfirst($targetType), (int) $report->target_id),
            'reason' => $this->joinReasonParts([
                (string) $report->reason,
                (string) ($report->message ?: ''),
            ]),
            'status' => (string) $report->status,
            'target' => [
                'type' => $targetType,
                'id' => (string) $report->target_id,
                'author' => $author !== '' ? $author : null,
                'summary' => $snippet !== '' ? $snippet : null,
            ],
        ];
    }

    private function normalizeQueueItem(Post $post, string $mode): array
    {
        $author = (string) ($post->user?->name ?: $post->user?->username ?: '');
        $snippet = $this->trimSnippet((string) $post->content);

        return [
            'kind' => 'queue',
            'id' => (string) $post->id,
            'created_at' => optional($post->created_at)->toIso8601String(),
            'label' => sprintf('Prispevok #%d', (int) $post->id),
            'reason' => $this->queueReason($post, $mode),
            'status' => $mode === 'reviewed' ? 'reviewed' : (string) $post->moderation_status,
            'target' => [
                'type' => 'post',
                'id' => (string) $post->id,
                'author' => $author !== '' ? $author : null,
                'summary' => $snippet !== '' ? $snippet : null,
            ],
        ];
    }

    private function queueReason(Post $post, string $mode): string
    {
        if ($mode === 'reviewed') {
            return 'Skontrolovane administratorom.';
        }

        if ((string) $post->moderation_status === 'pending') {
            return 'Caka na kontrolu.';
        }

        $scores = array_filter([
            $this->formatScore('tox', data_get($post->moderation_summary, 'text.toxicity_score')),
            $this->formatScore('hate', data_get($post->moderation_summary, 'text.hate_score')),
            $this->formatScore('nsfw', data_get($post->moderation_summary, 'attachment.nsfw_score')),
        ]);

        $reason = 'Automatická moderácia označila príspevok.';
        if ($scores !== []) {
            $reason .= ' ' . implode(' ', $scores);
        }

        return $reason;
    }

    private function reportFeedQuery(string $mode)
    {
        $query = Report::query()
            ->with([
                'target:id,content,user_id',
                'target.user:id,name,username',
            ])
            ->latest('created_at');

        if ($mode === 'reviewed') {
            return $query->where('status', '!=', 'open');
        }

        return $query->where('status', 'open');
    }

    private function queueFeedQuery(string $mode)
    {
        $query = Post::query()
            ->with(['user:id,name,username'])
            ->latest('created_at');

        if ($mode === 'reviewed') {
            return $query->whereIn('id', function ($subQuery) {
                $subQuery->select('entity_id')
                    ->from('moderation_logs')
                    ->where('entity_type', 'post')
                    ->whereNotNull('reviewed_by_admin_id');
            });
        }

        return $query->whereIn('moderation_status', ['pending', 'flagged']);
    }

    private function formatScore(string $label, mixed $value): ?string
    {
        if (!is_numeric($value)) {
            return null;
        }

        return sprintf('%s:%0.2f', $label, (float) $value);
    }

    private function normalizeTargetType(string $targetType): string
    {
        $segments = explode('\\', $targetType);
        $normalized = strtolower((string) end($segments));

        return $normalized !== '' ? $normalized : 'post';
    }

    private function trimSnippet(string $value, int $limit = 120): string
    {
        $value = trim($value);

        return $value === '' ? '' : mb_substr($value, 0, $limit);
    }

    private function joinReasonParts(array $parts): string
    {
        $values = Collection::make($parts)
            ->map(fn (mixed $value) => trim((string) $value))
            ->filter();

        return $values->isEmpty() ? '-' : $values->implode(' - ');
    }
}
