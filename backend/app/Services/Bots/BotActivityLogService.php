<?php

namespace App\Services\Bots;

use App\Models\BotActivityLog;
use App\Models\BotItem;
use App\Models\BotRun;
use App\Models\BotSource;
use Illuminate\Support\Facades\Log;
use Throwable;

class BotActivityLogService
{
    /**
     * @param array<string,mixed> $meta
     */
    public function record(
        string $action,
        string $outcome,
        ?BotItem $item = null,
        ?BotSource $source = null,
        ?BotRun $run = null,
        ?int $postId = null,
        ?string $reason = null,
        ?string $runContext = null,
        ?string $message = null,
        array $meta = [],
        ?int $actorUserId = null
    ): void {
        try {
            $resolvedSourceId = $source?->id ?? $item?->source_id ?? $run?->source_id;
            $resolvedRunId = $run?->id ?? $item?->run_id;
            $resolvedPostId = $postId ?? ($item?->post_id ? (int) $item->post_id : null);
            $botIdentity = $item?->bot_identity?->value
                ?? ($source?->bot_identity?->value ?? ($run?->bot_identity?->value ?? null));

            $payload = [
                'bot_identity' => $this->nullableString($botIdentity),
                'source_id' => $resolvedSourceId ? (int) $resolvedSourceId : null,
                'run_id' => $resolvedRunId ? (int) $resolvedRunId : null,
                'bot_item_id' => $item?->id ? (int) $item->id : null,
                'post_id' => $resolvedPostId,
                'actor_user_id' => $actorUserId,
                'action' => $this->limitText(strtolower(trim($action)), 50) ?: 'unknown',
                'outcome' => $this->limitText(strtolower(trim($outcome)), 20) ?: 'unknown',
                'reason' => $this->limitText($this->nullableString($reason) ?? '', 120),
                'run_context' => $this->limitText($this->nullableString($runContext) ?? '', 20),
                'message' => $this->limitText($this->nullableString($message) ?? '', 500),
                'meta' => $meta !== [] ? $meta : null,
            ];

            BotActivityLog::query()->create($payload);
        } catch (Throwable $exception) {
            Log::warning('Failed to persist bot activity log row.', [
                'action' => $action,
                'outcome' => $outcome,
                'reason' => $reason,
                'error' => $this->limitText($exception->getMessage(), 220),
            ]);
        }
    }

    private function nullableString(mixed $value): ?string
    {
        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : null;
    }

    private function limitText(string $value, int $maxLength): ?string
    {
        $normalized = trim($value);
        if ($normalized === '' || $maxLength <= 0) {
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

