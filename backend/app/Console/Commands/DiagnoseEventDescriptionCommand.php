<?php

namespace App\Console\Commands;

use App\Models\DescriptionGenerationRun;
use App\Models\Event;
use App\Models\EventCandidate;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DiagnoseEventDescriptionCommand extends Command
{
    private const ORIGINS_TABLE = 'event_description_origins';

    protected $signature = 'events:diagnose-description
                            {target : Event ID (default) or exact title with --title}
                            {--title : Resolve target as exact event title}
                            {--history=12 : Max history rows for origins/runs}';

    protected $description = 'Diagnose origin of event short/description content (AI vs fallback vs candidate/import/manual).';

    public function handle(): int
    {
        $target = trim((string) $this->argument('target'));
        $history = max(1, min(50, (int) $this->option('history')));
        $resolveByTitle = (bool) $this->option('title');

        $event = $this->resolveEvent($target, $resolveByTitle);
        if (! $event instanceof Event) {
            $this->error('Event not found.');
            return self::FAILURE;
        }

        $origins = $this->loadOrigins((int) $event->id, $history);
        $candidates = $this->loadCandidates((int) $event->id, $history);
        $runs = $this->loadRuns((int) $event->id, $history);
        $eventShortHash = $this->hashOptionalText((string) ($event->short ?? ''));
        $eventDescriptionHash = $this->hashOptionalText((string) ($event->description ?? ''));
        $currentOrigin = $this->resolveCurrentOrigin($origins, $eventDescriptionHash, $eventShortHash);
        $classification = $this->classify(
            event: $event,
            currentOrigin: $currentOrigin,
            origins: $origins,
            candidates: $candidates,
            runs: $runs
        );

        $payload = [
            'event' => [
                'id' => (int) $event->id,
                'title' => (string) $event->title,
                'source_name' => (string) ($event->source_name ?? ''),
                'source_uid' => (string) ($event->source_uid ?? ''),
                'updated_at' => $event->updated_at?->toIso8601String(),
                'short_preview' => $this->excerpt((string) ($event->short ?? ''), 220),
                'description_preview' => $this->excerpt((string) ($event->description ?? ''), 420),
                'short_hash' => $eventShortHash,
                'description_hash' => $eventDescriptionHash,
            ],
            'classification' => $classification,
            'current_origin' => $currentOrigin,
            'ai_guard_diagnostics' => $this->extractAiGuardDiagnostics($currentOrigin, $runs),
            'origins' => $origins,
            'candidate_links' => $candidates,
            'description_generation_runs' => $runs,
        ];

        $encoded = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (! is_string($encoded) || $encoded === '') {
            $this->error('Failed to encode diagnosis payload.');
            return self::FAILURE;
        }

        $this->line($encoded);

        return self::SUCCESS;
    }

    private function resolveEvent(string $target, bool $resolveByTitle): ?Event
    {
        if ($resolveByTitle) {
            if ($target === '') {
                return null;
            }

            return Event::query()
                ->where('title', $target)
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->first();
        }

        $eventId = (int) $target;
        if ($eventId <= 0) {
            return null;
        }

        return Event::query()->find($eventId);
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function loadOrigins(int $eventId, int $history): array
    {
        if (! Schema::hasTable(self::ORIGINS_TABLE)) {
            return [];
        }

        return DB::table(self::ORIGINS_TABLE)
            ->where('event_id', $eventId)
            ->orderByDesc('id')
            ->limit($history)
            ->get()
            ->map(static function ($row): array {
                $meta = null;
                if (isset($row->meta) && $row->meta !== null) {
                    if (is_string($row->meta)) {
                        $decoded = json_decode($row->meta, true);
                        $meta = is_array($decoded) ? $decoded : null;
                    } elseif (is_array($row->meta)) {
                        $meta = $row->meta;
                    }
                }

                return [
                    'id' => (int) ($row->id ?? 0),
                    'source' => (string) ($row->source ?? ''),
                    'source_detail' => $row->source_detail !== null ? (string) $row->source_detail : null,
                    'run_id' => $row->run_id !== null ? (int) $row->run_id : null,
                    'candidate_id' => $row->candidate_id !== null ? (int) $row->candidate_id : null,
                    'description_hash' => $row->description_hash !== null ? (string) $row->description_hash : null,
                    'short_hash' => $row->short_hash !== null ? (string) $row->short_hash : null,
                    'meta' => $meta,
                    'created_at' => $row->created_at !== null ? (string) $row->created_at : null,
                ];
            })
            ->all();
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function loadCandidates(int $eventId, int $history): array
    {
        return EventCandidate::query()
            ->where('published_event_id', $eventId)
            ->orderByDesc('id')
            ->limit($history)
            ->get([
                'id',
                'source_name',
                'status',
                'translation_status',
                'translation_error',
                'title',
                'translated_title',
                'description',
                'translated_description',
                'updated_at',
            ])
            ->map(fn (EventCandidate $candidate): array => [
                'id' => (int) $candidate->id,
                'source_name' => (string) $candidate->source_name,
                'status' => (string) $candidate->status,
                'translation_status' => (string) ($candidate->translation_status ?? ''),
                'translation_error' => $candidate->translation_error,
                'title_preview' => $this->excerpt((string) $candidate->title, 180),
                'translated_title_preview' => $this->excerpt((string) ($candidate->translated_title ?? ''), 180),
                'description_hash' => $this->hashOptionalText((string) ($candidate->description ?? '')),
                'translated_description_hash' => $this->hashOptionalText((string) ($candidate->translated_description ?? '')),
                'updated_at' => $candidate->updated_at?->toIso8601String(),
            ])
            ->all();
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function loadRuns(int $eventId, int $history): array
    {
        return DescriptionGenerationRun::query()
            ->where(function ($query) use ($eventId): void {
                $query->where('from_id', $eventId)
                    ->orWhereRaw(
                        "CAST(JSON_UNQUOTE(JSON_EXTRACT(meta, '$.last_processed_event_id')) AS UNSIGNED) = ?",
                        [$eventId]
                    );
            })
            ->orderByDesc('id')
            ->limit($history)
            ->get([
                'id',
                'status',
                'requested_mode',
                'effective_mode',
                'fallback_mode',
                'processed',
                'generated',
                'failed',
                'skipped',
                'meta',
                'updated_at',
            ])
            ->map(static function (DescriptionGenerationRun $run): array {
                $meta = is_array($run->meta) ? $run->meta : [];
                return [
                    'id' => (int) $run->id,
                    'status' => (string) $run->status,
                    'requested_mode' => (string) $run->requested_mode,
                    'effective_mode' => (string) $run->effective_mode,
                    'fallback_mode' => (string) $run->fallback_mode,
                    'processed' => (int) $run->processed,
                    'generated' => (int) $run->generated,
                    'failed' => (int) $run->failed,
                    'skipped' => (int) $run->skipped,
                    'last_event_status' => isset($meta['last_event_status']) ? (string) $meta['last_event_status'] : null,
                    'last_error_code' => isset($meta['last_error_code']) ? (string) $meta['last_error_code'] : null,
                    'updated_at' => $run->updated_at?->toIso8601String(),
                ];
            })
            ->all();
    }

    /**
     * @param array<string,mixed>|null $currentOrigin
     * @param array<int,array<string,mixed>> $origins
     * @param array<int,array<string,mixed>> $candidates
     * @param array<int,array<string,mixed>> $runs
     * @return array{mode:string,confidence:string,reason:string}
     */
    private function classify(
        Event $event,
        ?array $currentOrigin,
        array $origins,
        array $candidates,
        array $runs
    ): array
    {
        if ($currentOrigin !== null) {
            $latestOriginId = (int) ($origins[0]['id'] ?? 0);
            $currentOriginId = (int) ($currentOrigin['id'] ?? 0);
            $isLatest = $latestOriginId > 0 && $latestOriginId === $currentOriginId;

            return [
                'mode' => (string) ($currentOrigin['source'] ?? 'unknown'),
                'confidence' => $isLatest ? 'high' : 'medium',
                'reason' => $isLatest
                    ? 'Current text matches latest recorded source in event_description_origins.'
                    : 'Current text matches historical recorded source in event_description_origins.',
            ];
        }

        if ($origins !== []) {
            return [
                'mode' => 'origin_history_mismatch',
                'confidence' => 'low',
                'reason' => 'Origin history exists, but no recorded hash matches current event text.',
            ];
        }

        if ($candidates !== []) {
            $latest = $candidates[0];
            $hasTranslated = trim((string) ($latest['translated_description_hash'] ?? '')) !== '';

            return [
                'mode' => $hasTranslated ? 'candidate_translation' : 'candidate_import',
                'confidence' => 'medium',
                'reason' => 'Published event links to event_candidates, but no explicit provenance log exists.',
            ];
        }

        if ($runs !== []) {
            return [
                'mode' => 'ai_generation_untracked',
                'confidence' => 'low',
                'reason' => 'Description generation run references this event, but no source log is present.',
            ];
        }

        $sourceName = strtolower(trim((string) ($event->source_name ?? '')));
        if ($sourceName === 'manual') {
            return [
                'mode' => 'manual_event',
                'confidence' => 'medium',
                'reason' => 'Event source_name is manual and no explicit provenance log exists.',
            ];
        }

        return [
            'mode' => 'unknown',
            'confidence' => 'low',
            'reason' => 'No provenance log, candidate link, or matching AI run found.',
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $origins
     * @return array<string,mixed>|null
     */
    private function resolveCurrentOrigin(array $origins, ?string $eventDescriptionHash, ?string $eventShortHash): ?array
    {
        foreach ($origins as $origin) {
            if (! is_array($origin)) {
                continue;
            }

            if ($this->originMatchesCurrentHashes($origin, $eventDescriptionHash, $eventShortHash)) {
                return $origin;
            }
        }

        if ($eventDescriptionHash === null && $eventShortHash === null) {
            return $origins[0] ?? null;
        }

        return null;
    }

    /**
     * @param array<string,mixed> $origin
     */
    private function originMatchesCurrentHashes(array $origin, ?string $eventDescriptionHash, ?string $eventShortHash): bool
    {
        $originDescriptionHash = $this->normalizeOptionalString($origin['description_hash'] ?? null);
        $originShortHash = $this->normalizeOptionalString($origin['short_hash'] ?? null);

        $descriptionMatch = $originDescriptionHash !== null
            && $eventDescriptionHash !== null
            && hash_equals($originDescriptionHash, $eventDescriptionHash);
        $shortMatch = $originShortHash !== null
            && $eventShortHash !== null
            && hash_equals($originShortHash, $eventShortHash);

        if ($originDescriptionHash !== null && $originShortHash !== null && $eventDescriptionHash !== null && $eventShortHash !== null) {
            return $descriptionMatch && $shortMatch;
        }

        return $descriptionMatch || $shortMatch;
    }

    /**
     * @param array<string,mixed>|null $currentOrigin
     * @param array<int,array<string,mixed>> $runs
     * @return array<string,mixed>|null
     */
    private function extractAiGuardDiagnostics(?array $currentOrigin, array $runs): ?array
    {
        if (! is_array($currentOrigin)) {
            return null;
        }

        $source = strtolower(trim((string) ($currentOrigin['source'] ?? '')));
        if ($source !== 'ai_generation') {
            return null;
        }

        $meta = is_array($currentOrigin['meta'] ?? null) ? (array) $currentOrigin['meta'] : [];
        $generationDiagnostics = is_array($meta['generation_diagnostics'] ?? null)
            ? (array) $meta['generation_diagnostics']
            : [];

        $errorCodes = $this->normalizeStringList($generationDiagnostics['error_codes'] ?? null);
        $validationStage = trim((string) ($generationDiagnostics['validation_stage'] ?? ''));

        $lastRun = $runs[0] ?? null;
        $lastRunErrorCode = is_array($lastRun) ? $this->normalizeOptionalString($lastRun['last_error_code'] ?? null) : null;

        if ($errorCodes === [] && $validationStage === '' && $lastRunErrorCode === null) {
            return null;
        }

        return [
            'source_detail' => (string) ($currentOrigin['source_detail'] ?? ''),
            'validation_stage' => $validationStage !== '' ? $validationStage : null,
            'error_codes' => $errorCodes,
            'raw_output_excerpt' => isset($generationDiagnostics['raw_output_excerpt'])
                ? $this->excerpt((string) $generationDiagnostics['raw_output_excerpt'], 520)
                : null,
            'last_run_error_code' => $lastRunErrorCode,
        ];
    }

    private function excerpt(string $value, int $max): string
    {
        $normalized = trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
        if ($normalized === '') {
            return '';
        }

        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            if (mb_strlen($normalized, 'UTF-8') <= $max) {
                return $normalized;
            }

            return mb_substr($normalized, 0, $max, 'UTF-8') . '...';
        }

        if (strlen($normalized) <= $max) {
            return $normalized;
        }

        return substr($normalized, 0, $max) . '...';
    }

    private function hashOptionalText(string $value): ?string
    {
        $normalized = trim($value);
        if ($normalized === '') {
            return null;
        }

        return hash('sha256', $normalized);
    }

    private function normalizeOptionalString(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : null;
    }

    /**
     * @return array<int,string>
     */
    private function normalizeStringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map(
            static fn (mixed $item): string => trim((string) $item),
            $value
        ), static fn (string $item): bool => $item !== '')));
    }
}
