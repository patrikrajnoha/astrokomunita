<?php

namespace App\Http\Controllers\Api\Admin\Concerns;

use App\Enums\BotPublishStatus;
use App\Enums\BotRunFailureReason;
use App\Models\BotActivityLog;
use App\Models\BotItem;
use App\Models\BotRun;
use App\Models\BotSchedule;
use App\Models\BotSource;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

trait SerializesBotAdminData
{
    /**
     * @param \Illuminate\Support\Collection<int,BotSource> $sources
     * @return array<int,array<string,mixed>>
     */
    private function sourceMetricsBySourceId(\Illuminate\Support\Collection $sources): array
    {
        $sourceIds = $sources
            ->map(static fn (BotSource $source): int => (int) $source->id)
            ->filter(static fn (int $id): bool => $id > 0)
            ->values();

        if ($sourceIds->isEmpty()) {
            return [];
        }

        $windowStart = now()->subDay();
        $rows = BotActivityLog::query()
            ->where('created_at', '>=', $windowStart)
            ->whereIn('source_id', $sourceIds->all())
            ->selectRaw('
                source_id,
                SUM(CASE WHEN action = \'run\' THEN 1 ELSE 0 END) as runs_total,
                SUM(CASE WHEN action = \'run\' AND outcome in (\'success\',\'partial\') THEN 1 ELSE 0 END) as success_total,
                SUM(CASE WHEN action = \'run\' AND outcome = \'failed\' THEN 1 ELSE 0 END) as failure_total,
                SUM(CASE WHEN action = \'ingest\' AND outcome = \'skipped_duplicate\' THEN 1 ELSE 0 END) as duplicates_total,
                SUM(CASE WHEN action = \'ingest\' AND outcome in (\'created\',\'updated\',\'skipped_duplicate\') THEN 1 ELSE 0 END) as ingest_attempts_total,
                SUM(CASE WHEN action = \'skipped_cooldown\' THEN 1 ELSE 0 END) as cooldown_skips_total
            ')
            ->groupBy('source_id')
            ->get();

        $metrics = [];
        foreach ($rows as $row) {
            $sourceId = (int) ($row->source_id ?? 0);
            if ($sourceId <= 0) {
                continue;
            }

            $successTotal = (int) ($row->success_total ?? 0);
            $failureTotal = (int) ($row->failure_total ?? 0);
            $resolvedRuns = $successTotal + $failureTotal;
            $duplicatesTotal = (int) ($row->duplicates_total ?? 0);
            $ingestAttemptsTotal = (int) ($row->ingest_attempts_total ?? 0);

            $metrics[$sourceId] = [
                'runs_total' => (int) ($row->runs_total ?? 0),
                'success_total' => $successTotal,
                'failure_total' => $failureTotal,
                'duplicates_total' => $duplicatesTotal,
                'cooldown_skips_total' => (int) ($row->cooldown_skips_total ?? 0),
                'success_rate' => $resolvedRuns > 0
                    ? round($successTotal / $resolvedRuns, 4)
                    : null,
                'failure_rate' => $resolvedRuns > 0
                    ? round($failureTotal / $resolvedRuns, 4)
                    : null,
                'duplicate_rate' => $ingestAttemptsTotal > 0
                    ? round($duplicatesTotal / $ingestAttemptsTotal, 4)
                    : null,
            ];
        }

        return $metrics;
    }

    /**
     * @return array<string,mixed>
     */
    private function serializeSource(BotSource $source, array $metrics = []): array
    {
        $snapshot = $this->botSourceHealthPolicy->snapshot($source);
        $consecutiveFailures = max(0, (int) ($snapshot['consecutive_failures'] ?? 0));
        $status = (string) ($snapshot['status'] ?? 'ok');
        $isDead = (bool) ($snapshot['is_dead'] ?? false);

        return [
            'id' => $source->id,
            'key' => (string) $source->key,
            'name' => $this->nullableString($source->name) ?? (string) $source->key,
            'bot_identity' => $source->bot_identity?->value ?? (string) $source->bot_identity,
            'source_type' => $source->source_type?->value ?? (string) $source->source_type,
            'url' => (string) $source->url,
            'is_enabled' => (bool) $source->is_enabled,
            'status' => $status,
            'is_dead' => $isDead,
            'last_run_at' => $source->last_run_at?->toIso8601String(),
            'last_success_at' => $source->last_success_at?->toIso8601String(),
            'last_error_at' => $source->last_error_at?->toIso8601String(),
            'last_error_message' => $this->nullableString($source->last_error_message),
            'consecutive_failures' => $consecutiveFailures,
            'last_status_code' => $source->last_status_code !== null ? (int) $source->last_status_code : null,
            'avg_latency_ms' => $source->avg_latency_ms !== null ? (int) $source->avg_latency_ms : null,
            'cooldown_until' => $source->cooldown_until?->toIso8601String(),
            'metrics_24h' => [
                'runs_total' => (int) ($metrics['runs_total'] ?? 0),
                'success_total' => (int) ($metrics['success_total'] ?? 0),
                'failure_total' => (int) ($metrics['failure_total'] ?? 0),
                'duplicates_total' => (int) ($metrics['duplicates_total'] ?? 0),
                'cooldown_skips_total' => (int) ($metrics['cooldown_skips_total'] ?? 0),
                'success_rate' => $this->nullableRate($metrics['success_rate'] ?? null),
                'failure_rate' => $this->nullableRate($metrics['failure_rate'] ?? null),
                'duplicate_rate' => $this->nullableRate($metrics['duplicate_rate'] ?? null),
            ],
        ];
    }

    private function nullableRate(mixed $value): ?float
    {
        if (!is_numeric($value)) {
            return null;
        }

        $rate = (float) $value;
        if ($rate < 0) {
            return null;
        }

        return round(min(1.0, $rate), 4);
    }

    /**
     * @return array<string,mixed>
     */
    private function serializeSchedule(BotSchedule $schedule): array
    {
        return [
            'id' => $schedule->id,
            'bot_user_id' => $schedule->bot_user_id,
            'source_id' => $schedule->source_id,
            'enabled' => (bool) $schedule->enabled,
            'interval_minutes' => (int) $schedule->interval_minutes,
            'jitter_seconds' => (int) $schedule->jitter_seconds,
            'timezone' => $this->nullableString($schedule->timezone),
            'last_run_at' => $schedule->last_run_at?->toIso8601String(),
            'next_run_at' => $schedule->next_run_at?->toIso8601String(),
            'last_result' => $this->nullableString($schedule->last_result),
            'last_message' => $this->nullableString($schedule->last_message),
            'bot_user' => $schedule->botUser ? [
                'id' => $schedule->botUser->id,
                'username' => (string) $schedule->botUser->username,
                'role' => (string) ($schedule->botUser->role ?: ''),
                'is_bot' => (bool) $schedule->botUser->is_bot,
            ] : null,
            'source' => $schedule->source ? [
                'id' => $schedule->source->id,
                'key' => (string) $schedule->source->key,
                'name' => $this->nullableString($schedule->source->name) ?? (string) $schedule->source->key,
                'bot_identity' => $schedule->source->bot_identity?->value ?? (string) $schedule->source->bot_identity,
                'source_type' => $schedule->source->source_type?->value ?? (string) $schedule->source->source_type,
                'is_enabled' => (bool) $schedule->source->is_enabled,
            ] : null,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function serializeRun(BotRun $run): array
    {
        return [
            'id' => $run->id,
            'source_id' => $run->source_id,
            'source_key' => $run->source?->key,
            'bot_identity' => $run->bot_identity?->value ?? (string) $run->bot_identity,
            'status' => $run->status?->value ?? (string) $run->status,
            'started_at' => $run->started_at?->toIso8601String(),
            'finished_at' => $run->finished_at?->toIso8601String(),
            'stats' => is_array($run->stats) ? $run->stats : [],
            'meta' => is_array($run->meta) ? $run->meta : [],
            'error_text' => $this->truncateErrorText($run->error_text),
            'failure_reason' => BotRunFailureReason::fromNullable(data_get($run->meta, 'failure_reason'))->value,
            'ui_message' => $this->nullableString(data_get($run->meta, 'ui_message')),
            'cooldown_until' => $this->nullableString(data_get($run->meta, 'cooldown_until')),
        ];
    }

    /**
     * @return array{0:Carbon,1:Carbon}
     */
    private function resolveRunWindow(BotRun $run): array
    {
        $start = ($run->started_at?->copy() ?? now())->subMinutes(2);
        $end = ($run->finished_at?->copy() ?? now())->addMinutes(2);

        if ($end->lt($start)) {
            [$start, $end] = [$end, $start];
        }

        return [$start, $end];
    }

    /**
     * @return array<string,mixed>
     */
    private function serializeItem(BotItem $item): array
    {
        $meta = is_array($item->meta) ? $item->meta : [];

        $titleOriginal = trim((string) $item->title);
        $titleTranslated = trim((string) $item->title_translated);
        $resolvedTitle = $titleTranslated !== '' ? $titleTranslated : $titleOriginal;
        $contentOriginal = trim((string) ($item->content ?: $item->summary ?: ''));
        $contentTranslated = trim((string) $item->content_translated);
        $resolvedContent = $contentTranslated !== '' ? $contentTranslated : $contentOriginal;

        return [
            'id' => $item->id,
            'run_id' => $item->run_id,
            'stable_key' => (string) $item->stable_key,
            'publish_status' => $item->publish_status?->value ?? (string) $item->publish_status,
            'translation_status' => $item->translation_status?->value ?? (string) $item->translation_status,
            'translation_provider' => $this->nullableString($item->translation_provider),
            'translation_error' => $this->nullableString($item->translation_error),
            'translated_at' => $item->translated_at?->toIso8601String(),
            'post_id' => $item->post_id,
            'url' => $item->url,
            'title' => $resolvedTitle,
            'title_original' => $titleOriginal,
            'title_translated' => $titleTranslated !== '' ? $titleTranslated : null,
            'content' => $resolvedContent !== '' ? $resolvedContent : null,
            'content_original' => $contentOriginal !== '' ? $contentOriginal : null,
            'content_translated' => $contentTranslated !== '' ? $contentTranslated : null,
            'fetched_at' => $item->fetched_at?->toIso8601String(),
            'published_at' => $item->published_at?->toIso8601String(),
            'skip_reason' => $this->nullableString(data_get($meta, 'skip_reason')),
            'used_translation' => $this->nullableBool(data_get($meta, 'used_translation')),
            'last_seen_run_id' => $this->nullableInt(data_get($meta, 'last_seen_run_id')),
            'published_manually' => $this->nullableBool(data_get($meta, 'published_manually')),
            'manual_published_at' => $this->nullableString(data_get($meta, 'manual_published_at')),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function serializeActivity(BotActivityLog $log): array
    {
        return [
            'id' => $log->id,
            'created_at' => $log->created_at?->toIso8601String(),
            'bot_identity' => $log->bot_identity?->value ?? (string) $log->bot_identity,
            'source_id' => $log->source_id,
            'source_key' => $log->source?->key,
            'run_id' => $log->run_id,
            'run_status' => $log->run?->status?->value ?? (string) ($log->run?->status ?? ''),
            'bot_item_id' => $log->bot_item_id,
            'stable_key' => $log->item?->stable_key,
            'post_id' => $log->post_id,
            'actor_user_id' => $log->actor_user_id,
            'action' => (string) $log->action,
            'outcome' => (string) $log->outcome,
            'reason' => $this->nullableString($log->reason),
            'run_context' => $this->nullableString($log->run_context),
            'message' => $this->nullableString($log->message),
            'meta' => is_array($log->meta) ? $log->meta : [],
        ];
    }

    private function defaultModeForSource(string $sourceKey): string
    {
        $configured = strtolower(trim((string) config(sprintf('bots.sources.%s.default_mode', $sourceKey), 'auto')));

        return in_array($configured, ['auto', 'dry'], true) ? $configured : 'auto';
    }

    private function runLinkedItemsQuery(BotRun $run): Builder
    {
        if (!$run->source_id) {
            return BotItem::query()->whereRaw('1 = 0');
        }

        return BotItem::query()
            ->where('source_id', (int) $run->source_id)
            ->where(function (Builder $query) use ($run): void {
                $query
                    ->where('run_id', $run->id)
                    ->orWhere('meta->last_seen_run_id', $run->id);
            });
    }

    private function isPublishedStatus(BotItem $item): bool
    {
        $status = strtolower(trim((string) ($item->publish_status?->value ?? $item->publish_status)));

        return $status === BotPublishStatus::PUBLISHED->value;
    }

    private function markItemPublishedManually(BotItem $item): BotItem
    {
        $meta = is_array($item->meta) ? $item->meta : [];
        $meta['published_manually'] = true;
        $meta['manual_published_at'] = now()->toIso8601String();

        $item->forceFill(['meta' => $meta])->save();

        return $item->fresh() ?? $item;
    }

    private function markItemPostVymazaneManually(BotItem $item, int $postId): BotItem
    {
        $meta = is_array($item->meta) ? $item->meta : [];
        $meta['deleted_manually'] = true;
        $meta['manual_deleted_at'] = now()->toIso8601String();
        $meta['deleted_post_id'] = $postId;
        unset($meta['published_to_posts_at']);

        $item->forceFill([
            'post_id' => null,
            'publish_status' => BotPublishStatus::PENDING->value,
            'meta' => $meta,
        ])->save();

        return $item->fresh() ?? $item;
    }

    private function nullableString(mixed $value): ?string
    {
        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : null;
    }

    private function nullableBool(mixed $value): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value === 1 ? true : ($value === 0 ? false : null);
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));
            if (in_array($normalized, ['1', 'true', 'yes'], true)) {
                return true;
            }
            if (in_array($normalized, ['0', 'false', 'no'], true)) {
                return false;
            }
        }

        return null;
    }

    private function nullableInt(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        return null;
    }

    private function truncateErrorText(?string $value, int $maxLength = 1000): ?string
    {
        $normalized = trim((string) $value);
        if ($normalized === '') {
            return null;
        }

        if ($maxLength <= 0) {
            return null;
        }

        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            if (mb_strlen($normalized) <= $maxLength) {
                return $normalized;
            }

            return mb_substr($normalized, 0, $maxLength);
        }

        if (strlen($normalized) <= $maxLength) {
            return $normalized;
        }

        return substr($normalized, 0, $maxLength);
    }
}
