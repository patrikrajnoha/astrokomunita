<?php

namespace App\Services\Bots;

use App\Enums\BotPublishStatus;
use App\Enums\BotTranslationStatus;
use App\Models\BotItem;
use App\Models\BotSource;

class BotItemDedupeService
{
    /**
     * @param array<string, mixed> $payload
     */
    public function upsertByStableKey(
        BotSource $source,
        string $stableKey,
        array $payload = [],
        ?int $runId = null
    ): BotItem
    {
        $existing = BotItem::query()
            ->where('source_id', $source->id)
            ->where('stable_key', $stableKey)
            ->first();

        $existingMeta = is_array($existing?->meta) ? $existing->meta : [];
        $payloadMeta = is_array($payload['meta'] ?? null) ? $payload['meta'] : [];
        $fetchedAt = $payload['fetched_at'] ?? now();
        $meta = array_replace($existingMeta, $payloadMeta);

        if ($existing && $runId !== null) {
            $meta['last_seen_run_id'] = $runId;
            $meta['seen_at'] = now()->toIso8601String();
        }

        $attributes = [
            'bot_identity' => $source->bot_identity?->value ?? (string) $source->bot_identity,
            'source_id' => $source->id,
            'run_id' => $runId,
            'post_id' => array_key_exists('post_id', $payload) ? ($payload['post_id'] ?: null) : $existing?->post_id,
            'stable_key' => $stableKey,
            'title' => (string) ($payload['title'] ?? ''),
            'summary' => $payload['summary'] ?? null,
            'content' => $payload['content'] ?? null,
            'url' => $payload['url'] ?? null,
            'published_at' => $payload['published_at'] ?? null,
            'fetched_at' => $fetchedAt,
            'lang_original' => $payload['lang_original'] ?? null,
            'lang_detected' => $payload['lang_detected'] ?? null,
            'title_translated' => $payload['title_translated'] ?? $existing?->title_translated,
            'content_translated' => $payload['content_translated'] ?? $existing?->content_translated,
            'translation_status' => $payload['translation_status']
                ?? ($existing?->translation_status?->value ?? BotTranslationStatus::PENDING->value),
            'publish_status' => $payload['publish_status']
                ?? ($existing?->publish_status?->value ?? BotPublishStatus::PENDING->value),
            'meta' => $meta,
        ];

        if ($existing) {
            unset($attributes['run_id']);
            $existing->fill($attributes)->save();
            return $existing;
        }

        return BotItem::query()->create($attributes);
    }
}
