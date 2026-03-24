<?php

namespace App\Services\Events;

use App\Models\Event;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class EventDescriptionReviewGateService
{
    private const ORIGINS_TABLE = 'event_description_origins';
    private const SOURCE_AI_GENERATION = 'ai_generation';

    /**
     * @return array{
     *   required:bool,
     *   reason_code:?string,
     *   message:?string,
     *   source:?string,
     *   source_detail:?string
     * }
     */
    public function evaluateForPublish(Event $event): array
    {
        $eventId = (int) ($event->id ?? 0);
        if ($eventId <= 0 || ! $this->isOriginsTableAvailable()) {
            return $this->result(false);
        }

        $origin = DB::table(self::ORIGINS_TABLE)
            ->where('event_id', $eventId)
            ->orderByDesc('id')
            ->first(['source', 'source_detail', 'meta']);

        if ($origin === null) {
            return $this->result(false);
        }

        $source = strtolower(trim((string) ($origin->source ?? '')));
        $sourceDetail = strtolower(trim((string) ($origin->source_detail ?? '')));

        if ($source !== self::SOURCE_AI_GENERATION) {
            return $this->result(false, null, null, $source, $sourceDetail);
        }

        $meta = $this->decodeMeta($origin->meta ?? null);
        $usedFallbackBase = (bool) ($meta['used_fallback_base'] ?? false);
        $hasDiagnostics = is_array($meta['generation_diagnostics'] ?? null)
            && (array) $meta['generation_diagnostics'] !== [];
        $detailSignalsFallback = $sourceDetail !== '' && str_contains($sourceDetail, 'fallback');

        if ($usedFallbackBase || $hasDiagnostics || $detailSignalsFallback) {
            $reason = $hasDiagnostics
                ? 'ai_guard_fallback'
                : ($usedFallbackBase ? 'ai_base_fallback' : 'ai_fallback');

            return $this->result(
                required: true,
                reasonCode: $reason,
                message: 'Udalost ma AI opis s fallback signalom. Pred zverejnenim ju skontroluj, alebo publikovanie potvrd explicitne.',
                source: $source,
                sourceDetail: $sourceDetail !== '' ? $sourceDetail : null
            );
        }

        return $this->result(false, null, null, $source, $sourceDetail !== '' ? $sourceDetail : null);
    }

    /**
     * @return array<string,mixed>
     */
    private function decodeMeta(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value)) {
            return [];
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return [];
        }

        $decoded = json_decode($trimmed, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function isOriginsTableAvailable(): bool
    {
        try {
            return Schema::hasTable(self::ORIGINS_TABLE);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @return array{
     *   required:bool,
     *   reason_code:?string,
     *   message:?string,
     *   source:?string,
     *   source_detail:?string
     * }
     */
    private function result(
        bool $required,
        ?string $reasonCode = null,
        ?string $message = null,
        ?string $source = null,
        ?string $sourceDetail = null
    ): array {
        return [
            'required' => $required,
            'reason_code' => $reasonCode,
            'message' => $message,
            'source' => $source,
            'source_detail' => $sourceDetail,
        ];
    }
}
