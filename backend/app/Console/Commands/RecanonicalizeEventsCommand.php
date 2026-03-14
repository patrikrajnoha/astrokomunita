<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Services\Events\CanonicalKeyService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RecanonicalizeEventsCommand extends Command
{
    protected $signature = 'events:recanonicalize
        {--source= : Only for specific source_name (e.g. astropixels)}
        {--dry : Preview mode, no database changes}
        {--all-types : Recompute canonical key for all event types (default: meteor_shower only)}
        {--merge-duplicates : Merge extra events with same canonical_key and rebind references}';

    protected $description = 'Recompute Event canonical key/fingerprint and optionally merge duplicate published events.';

    public function __construct(
        private readonly CanonicalKeyService $canonicalKeyService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $source = trim((string) ($this->option('source') ?? ''));
        $dry = (bool) $this->option('dry');
        $allTypes = (bool) $this->option('all-types');
        $mergeDuplicates = (bool) $this->option('merge-duplicates');

        $query = Event::query()
            ->select([
                'id',
                'source_name',
                'source_hash',
                'title',
                'type',
                'start_at',
                'max_at',
                'canonical_key',
                'fingerprint_v2',
                'matched_sources',
                'confidence_score',
                'short',
                'description',
                'updated_at',
            ]);

        if ($source !== '') {
            $query->where('source_name', $source);
        }

        $total = (clone $query)->count();
        $scanned = 0;
        $changed = 0;

        $query->orderBy('id')->chunkById(500, function ($rows) use (&$scanned, &$changed, $dry, $allTypes): void {
            foreach ($rows as $event) {
                $scanned++;

                $title = trim((string) $event->title);
                if ($title === '') {
                    continue;
                }

                $type = trim((string) $event->type);
                if ($type === '') {
                    $type = 'other';
                }

                $currentCanonical = $this->normalizeText((string) $event->canonical_key);
                $shouldRecanonicalize = $allTypes || $type === 'meteor_shower';
                $canonical = $currentCanonical;

                if ($shouldRecanonicalize || $canonical === null) {
                    $reference = $event->start_at ?? $event->max_at;
                    $canonical = $this->normalizeText($this->canonicalKeyService->make(
                        type: $type,
                        date: $reference ? CarbonImmutable::instance($reference)->utc() : null,
                        title: $title
                    ));
                }

                if ($canonical === null) {
                    continue;
                }

                $canonicalChanged = $currentCanonical !== $canonical;
                $currentFingerprint = $this->normalizeText((string) $event->fingerprint_v2);
                $fingerprint = $currentFingerprint;

                if ($currentFingerprint === null || $canonicalChanged) {
                    $fingerprint = $this->buildFingerprintV2(
                        canonicalKey: $canonical,
                        type: $type,
                        startAt: $event->start_at,
                        maxAt: $event->max_at,
                        title: $title,
                        sourceHash: is_string($event->source_hash) ? $event->source_hash : null,
                        currentFingerprint: is_string($event->fingerprint_v2) ? $event->fingerprint_v2 : null
                    );
                }

                $fingerprintChanged = $currentFingerprint !== $fingerprint;

                if (! $canonicalChanged && ! $fingerprintChanged) {
                    continue;
                }

                $changed++;
                if ($dry) {
                    continue;
                }

                $event->canonical_key = $canonical;
                $event->fingerprint_v2 = $fingerprint;
                $event->save();
            }
        });

        $signalsUpdated = 0;
        $duplicatesMerged = 0;
        if (! $dry) {
            [$signalsUpdated, $duplicatesMerged] = $this->syncCanonicalSignals(
                source: $source !== '' ? $source : null,
                mergeDuplicates: $mergeDuplicates
            );
        }

        $this->info("Scanned: {$scanned} / {$total}");
        $this->info('Changed: '.$changed.($dry ? ' (dry-run)' : ''));
        if (! $dry) {
            $this->info("Signals updated: {$signalsUpdated}");
            if ($mergeDuplicates) {
                $this->info("Events merged: {$duplicatesMerged}");
            }
        }

        return self::SUCCESS;
    }

    /**
     * @return array{0:int,1:int}
     */
    private function syncCanonicalSignals(?string $source, bool $mergeDuplicates): array
    {
        $query = Event::query()
            ->whereNotNull('canonical_key')
            ->where('canonical_key', '!=', '')
            ->select([
                'id',
                'source_name',
                'canonical_key',
                'matched_sources',
                'confidence_score',
                'short',
                'description',
                'fingerprint_v2',
                'updated_at',
            ]);

        if ($source !== null) {
            $query->where('source_name', $source);
        }

        $groups = $query->orderBy('id')->get()->groupBy('canonical_key');
        $signalsUpdated = 0;
        $duplicatesMerged = 0;

        foreach ($groups as $group) {
            $mergedSources = [];
            foreach ($group as $event) {
                $mergedSources[] = (string) $event->source_name;
                if (is_array($event->matched_sources)) {
                    $mergedSources = array_merge($mergedSources, $event->matched_sources);
                }
            }

            $normalizedSources = $this->normalizeMatchedSources($mergedSources);
            $resolvedScore = $normalizedSources !== null && count($normalizedSources) >= 2 ? 1.0 : 0.7;

            foreach ($group as $event) {
                $currentSources = $this->normalizeMatchedSources(
                    is_array($event->matched_sources) ? $event->matched_sources : []
                );
                $currentScore = $event->confidence_score !== null ? round((float) $event->confidence_score, 2) : null;
                $needsUpdate = $currentSources !== $normalizedSources || $currentScore !== $resolvedScore;

                if (! $needsUpdate) {
                    continue;
                }

                $event->matched_sources = $normalizedSources;
                $event->confidence_score = $resolvedScore;
                $event->save();
                $signalsUpdated++;
            }

            if (! $mergeDuplicates) {
                continue;
            }

            $sorted = $group->sort(function (Event $left, Event $right): int {
                return $this->duplicateRank($right) <=> $this->duplicateRank($left);
            })->values();

            if ($sorted->count() <= 1) {
                continue;
            }

            $keeper = $sorted->first();
            if (! $keeper instanceof Event) {
                continue;
            }

            $duplicates = $sorted
                ->slice(1)
                ->filter(fn ($event): bool => $event instanceof Event)
                ->values();

            foreach ($duplicates as $duplicate) {
                if (! $duplicate instanceof Event) {
                    continue;
                }

                $merged = $this->mergeDuplicateIntoKeeper(
                    keeperId: (int) $keeper->id,
                    duplicateId: (int) $duplicate->id,
                    normalizedSources: $normalizedSources,
                    resolvedScore: $resolvedScore
                );
                if ($merged) {
                    $duplicatesMerged++;
                }
            }
        }

        return [$signalsUpdated, $duplicatesMerged];
    }

    /**
     * @param  array<int,string>|null  $normalizedSources
     */
    private function mergeDuplicateIntoKeeper(
        int $keeperId,
        int $duplicateId,
        ?array $normalizedSources,
        float $resolvedScore
    ): bool {
        if ($keeperId <= 0 || $duplicateId <= 0 || $keeperId === $duplicateId) {
            return false;
        }

        return DB::transaction(function () use ($keeperId, $duplicateId, $normalizedSources, $resolvedScore): bool {
            $keeper = Event::query()->find($keeperId);
            $duplicate = Event::query()->find($duplicateId);

            if (! $keeper instanceof Event || ! $duplicate instanceof Event) {
                return false;
            }

            $this->rebindEventReferences($duplicateId, $keeperId);

            $dirty = false;
            if ($this->normalizeText((string) $keeper->short) === null && $this->normalizeText((string) $duplicate->short) !== null) {
                $keeper->short = $duplicate->short;
                $dirty = true;
            }
            if ($this->normalizeText((string) $keeper->description) === null && $this->normalizeText((string) $duplicate->description) !== null) {
                $keeper->description = $duplicate->description;
                $dirty = true;
            }
            if ($this->normalizeText((string) $keeper->fingerprint_v2) === null && $this->normalizeText((string) $duplicate->fingerprint_v2) !== null) {
                $keeper->fingerprint_v2 = $duplicate->fingerprint_v2;
                $dirty = true;
            }

            if ($this->normalizeMatchedSources(
                is_array($keeper->matched_sources) ? $keeper->matched_sources : []
            ) !== $normalizedSources) {
                $keeper->matched_sources = $normalizedSources;
                $dirty = true;
            }

            $keeperScore = $keeper->confidence_score !== null ? round((float) $keeper->confidence_score, 2) : null;
            if ($keeperScore !== $resolvedScore) {
                $keeper->confidence_score = $resolvedScore;
                $dirty = true;
            }

            if ($dirty) {
                $keeper->save();
            }

            $duplicate->delete();

            return true;
        });
    }

    private function rebindEventReferences(int $fromEventId, int $toEventId): void
    {
        if ($fromEventId <= 0 || $toEventId <= 0 || $fromEventId === $toEventId) {
            return;
        }

        $this->rebindUniqueBySecondaryColumn('user_event_follows', 'event_id', 'user_id', $fromEventId, $toEventId);
        $this->rebindUniqueBySecondaryColumn('favorites', 'event_id', 'user_id', $fromEventId, $toEventId);
        $this->rebindUniqueBySecondaryColumn('event_reminders', 'event_id', 'user_id', $fromEventId, $toEventId);
        $this->rebindUniqueBySecondaryColumn('event_email_alerts', 'event_id', 'email', $fromEventId, $toEventId);
        $this->rebindMonthlyFeaturedEvents($fromEventId, $toEventId);

        $this->rebindSimpleReference('newsletter_featured_events', 'event_id', $fromEventId, $toEventId);
        $this->rebindSimpleReference('event_invites', 'event_id', $fromEventId, $toEventId);
        $this->rebindSimpleReference('event_candidates', 'published_event_id', $fromEventId, $toEventId);
        $this->rebindSimpleReference('manual_events', 'published_event_id', $fromEventId, $toEventId);
        $this->rebindSimpleReference('observations', 'event_id', $fromEventId, $toEventId);
        $this->rebindSimpleReference('description_generation_runs', 'last_event_id', $fromEventId, $toEventId);
    }

    private function rebindSimpleReference(string $table, string $column, int $fromEventId, int $toEventId): void
    {
        if (! $this->tableHasColumns($table, [$column])) {
            return;
        }

        DB::table($table)
            ->where($column, $fromEventId)
            ->update([$column => $toEventId]);
    }

    private function rebindUniqueBySecondaryColumn(
        string $table,
        string $eventColumn,
        string $secondaryColumn,
        int $fromEventId,
        int $toEventId
    ): void {
        if (! $this->tableHasColumns($table, [$eventColumn, $secondaryColumn])) {
            return;
        }

        $existingSecondaryValues = DB::table($table)
            ->where($eventColumn, $toEventId)
            ->pluck($secondaryColumn)
            ->filter(static fn ($value): bool => $value !== null && $value !== '')
            ->values()
            ->all();

        if ($existingSecondaryValues !== []) {
            DB::table($table)
                ->where($eventColumn, $fromEventId)
                ->whereIn($secondaryColumn, $existingSecondaryValues)
                ->delete();
        }

        DB::table($table)
            ->where($eventColumn, $fromEventId)
            ->update([$eventColumn => $toEventId]);
    }

    private function rebindMonthlyFeaturedEvents(int $fromEventId, int $toEventId): void
    {
        if (! $this->tableHasColumns('monthly_featured_events', ['event_id'])) {
            return;
        }

        if (Schema::hasColumn('monthly_featured_events', 'month_key')) {
            $existingMonthKeys = DB::table('monthly_featured_events')
                ->where('event_id', $toEventId)
                ->pluck('month_key')
                ->filter(static fn ($value): bool => $value !== null && trim((string) $value) !== '')
                ->map(static fn ($value): string => trim((string) $value))
                ->values()
                ->all();

            if ($existingMonthKeys !== []) {
                DB::table('monthly_featured_events')
                    ->where('event_id', $fromEventId)
                    ->whereIn('month_key', $existingMonthKeys)
                    ->delete();
            }
        } elseif (DB::table('monthly_featured_events')->where('event_id', $toEventId)->exists()) {
            DB::table('monthly_featured_events')
                ->where('event_id', $fromEventId)
                ->delete();
        }

        DB::table('monthly_featured_events')
            ->where('event_id', $fromEventId)
            ->update(['event_id' => $toEventId]);
    }

    /**
     * @return array{0:float,1:int,2:int,3:int}
     */
    private function duplicateRank(Event $event): array
    {
        $confidence = is_numeric((string) $event->confidence_score) ? (float) $event->confidence_score : 0.0;
        $matchedSources = is_array($event->matched_sources) ? count($event->matched_sources) : 0;
        $updatedAt = $event->updated_at ? CarbonImmutable::instance($event->updated_at)->timestamp : 0;

        return [
            round($confidence, 4),
            $matchedSources,
            $updatedAt,
            (int) $event->id,
        ];
    }

    private function buildFingerprintV2(
        string $canonicalKey,
        string $type,
        ?\DateTimeInterface $startAt,
        ?\DateTimeInterface $maxAt,
        string $title,
        ?string $sourceHash,
        ?string $currentFingerprint
    ): string {
        $parts = [];

        $normalizedCanonical = $this->normalizeForSimilarity($canonicalKey);
        if ($normalizedCanonical !== null) {
            $parts[] = 'ck:'.$normalizedCanonical;
        }

        $normalizedType = $this->normalizeForSimilarity($type);
        if ($normalizedType !== null) {
            $parts[] = 'tp:'.$normalizedType;
        }

        $anchor = $this->resolveReferenceMoment($startAt, $maxAt);
        if ($anchor !== null) {
            $parts[] = 'dt:'.CarbonImmutable::instance($anchor)->utc()->toDateString();
        }

        $normalizedTitle = $this->normalizeForSimilarity($title);
        if ($normalizedTitle !== null) {
            $parts[] = 'ttl:'.$normalizedTitle;
        }

        if ($parts !== []) {
            return hash('sha256', implode('|', $parts));
        }

        $fallbacks = [
            $this->normalizeText($sourceHash),
            $this->normalizeText($currentFingerprint),
        ];

        foreach ($fallbacks as $fallback) {
            if ($fallback !== null) {
                return $fallback;
            }
        }

        return hash('sha256', $canonicalKey.'|'.$type.'|'.$title);
    }

    private function resolveReferenceMoment(
        ?\DateTimeInterface $startAt,
        ?\DateTimeInterface $maxAt
    ): ?\DateTimeInterface {
        if ($startAt !== null) {
            return $startAt;
        }

        if ($maxAt !== null) {
            return $maxAt;
        }

        return null;
    }

    private function normalizeForSimilarity(?string $value): ?string
    {
        $normalized = $this->normalizeText($value);
        if ($normalized === null) {
            return null;
        }

        if (function_exists('mb_strtolower')) {
            $normalized = mb_strtolower($normalized, 'UTF-8');
        } else {
            $normalized = strtolower($normalized);
        }

        $normalized = preg_replace('/[^\pL\pN\s]/u', ' ', $normalized) ?? $normalized;
        $normalized = preg_replace('/\s+/u', ' ', $normalized) ?? $normalized;
        $normalized = trim($normalized);

        return $normalized !== '' ? $normalized : null;
    }

    private function normalizeText(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim($value);
        $normalized = preg_replace('/\s+/u', ' ', $normalized) ?? $normalized;
        $normalized = trim($normalized);

        return $normalized !== '' ? $normalized : null;
    }

    /**
     * @param  array<int,mixed>|null  $sources
     * @return array<int,string>|null
     */
    private function normalizeMatchedSources(?array $sources): ?array
    {
        if ($sources === null) {
            return null;
        }

        $normalized = array_values(array_unique(array_filter(array_map(
            static fn (mixed $item): string => strtolower(trim((string) $item)),
            $sources
        ), static fn (string $item): bool => $item !== '')));

        if ($normalized === []) {
            return null;
        }

        sort($normalized);

        return $normalized;
    }

    /**
     * @param  array<int,string>  $columns
     */
    private function tableHasColumns(string $table, array $columns): bool
    {
        if (! Schema::hasTable($table)) {
            return false;
        }

        foreach ($columns as $column) {
            if (! Schema::hasColumn($table, $column)) {
                return false;
            }
        }

        return true;
    }
}
