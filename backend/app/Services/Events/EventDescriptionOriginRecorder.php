<?php

namespace App\Services\Events;

use App\Models\Event;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class EventDescriptionOriginRecorder
{
    private const TABLE = 'event_description_origins';

    /**
     * @param array<string,mixed> $meta
     */
    public function record(
        Event $event,
        string $source,
        ?string $sourceDetail = null,
        ?int $runId = null,
        ?int $candidateId = null,
        array $meta = []
    ): void {
        $eventId = (int) ($event->id ?? 0);
        $normalizedSource = trim($source);

        if ($eventId <= 0 || $normalizedSource === '' || ! $this->isTableAvailable()) {
            return;
        }

        try {
            $normalizedMeta = $this->normalizeMeta($meta);

            DB::table(self::TABLE)->insert([
                'event_id' => $eventId,
                'source' => $normalizedSource,
                'source_detail' => $this->normalizeOptionalString($sourceDetail),
                'run_id' => $runId && $runId > 0 ? $runId : null,
                'candidate_id' => $candidateId && $candidateId > 0 ? $candidateId : null,
                'description_hash' => $this->hashOptionalText((string) ($event->description ?? '')),
                'short_hash' => $this->hashOptionalText((string) ($event->short ?? '')),
                'meta' => $this->encodeMeta($normalizedMeta),
                'created_at' => now(),
            ]);
        } catch (Throwable $exception) {
            Log::warning('Failed to record event description origin.', [
                'event_id' => $eventId,
                'source' => $normalizedSource,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function isTableAvailable(): bool
    {
        try {
            return Schema::hasTable(self::TABLE);
        } catch (Throwable) {
            return false;
        }
    }

    private function normalizeOptionalString(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim($value);

        return $normalized !== '' ? $normalized : null;
    }

    private function hashOptionalText(string $value): ?string
    {
        $normalized = trim($value);
        if ($normalized === '') {
            return null;
        }

        return hash('sha256', $normalized);
    }

    /**
     * @param array<string,mixed> $meta
     * @return array<string,mixed>
     */
    private function normalizeMeta(array $meta): array
    {
        $normalized = [];

        foreach ($meta as $key => $value) {
            $key = trim((string) $key);
            if ($key === '') {
                continue;
            }

            $normalized[$key] = $this->normalizeMetaValue($value);
        }

        return $normalized;
    }

    /**
     * @param array<string,mixed> $meta
     */
    private function encodeMeta(array $meta): ?string
    {
        if ($meta === []) {
            return null;
        }

        $encoded = json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return is_string($encoded) && $encoded !== '' ? $encoded : null;
    }

    private function normalizeMetaValue(mixed $value): mixed
    {
        if (is_null($value) || is_scalar($value)) {
            return $value;
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format(DateTimeInterface::ATOM);
        }

        if (is_array($value)) {
            $normalized = [];
            foreach ($value as $key => $item) {
                if (is_int($key) || is_string($key)) {
                    $normalized[$key] = $this->normalizeMetaValue($item);
                }
            }

            return $normalized;
        }

        if (is_object($value) && method_exists($value, '__toString')) {
            return (string) $value;
        }

        return null;
    }
}
