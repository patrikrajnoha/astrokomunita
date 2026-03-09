<?php

namespace App\Console\Commands;

use App\Models\EventCandidate;
use App\Services\EventImport\EventTypeClassifier;
use App\Services\Events\CanonicalKeyService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class RecanonicalizeEventCandidatesCommand extends Command
{
    protected $signature = 'events:candidates:recanonicalize
        {--source= : Only for specific source_name (e.g. astropixels)}
        {--status= : Only for specific status (e.g. pending)}
        {--dry : Preview mode, no database changes}
        {--merge-pending-duplicates : Mark extra pending rows with same canonical_key as duplicate}';

    protected $description = 'Recompute EventCandidate type/canonical key and sync canonical signals.';

    public function __construct(
        private readonly EventTypeClassifier $classifier,
        private readonly CanonicalKeyService $canonicalKeyService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $source = trim((string) ($this->option('source') ?? ''));
        $status = trim((string) ($this->option('status') ?? ''));
        $dry = (bool) $this->option('dry');
        $mergePendingDuplicates = (bool) $this->option('merge-pending-duplicates');

        $query = EventCandidate::query()
            ->select([
                'id',
                'source_name',
                'status',
                'raw_type',
                'title',
                'type',
                'start_at',
                'max_at',
                'canonical_key',
                'matched_sources',
                'confidence_score',
            ]);

        if ($source !== '') {
            $query->where('source_name', $source);
        }
        if ($status !== '') {
            $query->where('status', $status);
        }

        $total = (clone $query)->count();
        $scanned = 0;
        $changed = 0;

        $query->orderBy('id')->chunkById(500, function ($rows) use (&$scanned, &$changed, $dry): void {
            foreach ($rows as $candidate) {
                $scanned++;

                $title = trim((string) $candidate->title);
                if ($title === '') {
                    continue;
                }

                $normalizedType = $this->classifier->classify($candidate->raw_type, $candidate->title);
                $reference = $candidate->start_at ?? $candidate->max_at;
                $canonical = $this->canonicalKeyService->make(
                    type: $normalizedType,
                    date: $reference ? CarbonImmutable::instance($reference)->utc() : null,
                    title: $title
                );
                $canonical = $this->normalizeCanonical($canonical);
                if ($canonical === null) {
                    continue;
                }

                $currentType = trim((string) $candidate->type);
                $currentCanonical = $this->normalizeCanonical((string) $candidate->canonical_key);
                $typeChanged = $currentType !== $normalizedType;
                $canonicalChanged = $currentCanonical !== $canonical;

                if (! $typeChanged && ! $canonicalChanged) {
                    continue;
                }

                $changed++;
                if ($dry) {
                    continue;
                }

                $candidate->type = $normalizedType;
                $candidate->canonical_key = $canonical;
                $candidate->save();
            }
        });

        $signalsUpdated = 0;
        $duplicatesMerged = 0;
        if (! $dry) {
            [$signalsUpdated, $duplicatesMerged] = $this->syncCanonicalSignals(
                source: $source !== '' ? $source : null,
                status: $status !== '' ? $status : null,
                mergePendingDuplicates: $mergePendingDuplicates
            );
        }

        $this->info("Scanned: {$scanned} / {$total}");
        $this->info('Changed: '.$changed.($dry ? ' (dry-run)' : ''));
        if (! $dry) {
            $this->info("Signals updated: {$signalsUpdated}");
            if ($mergePendingDuplicates) {
                $this->info("Pending duplicates merged: {$duplicatesMerged}");
            }
        }

        return self::SUCCESS;
    }

    /**
     * @return array{0:int,1:int}
     */
    private function syncCanonicalSignals(?string $source, ?string $status, bool $mergePendingDuplicates): array
    {
        $query = EventCandidate::query()
            ->whereNotNull('canonical_key')
            ->where('canonical_key', '!=', '')
            ->select([
                'id',
                'source_name',
                'status',
                'canonical_key',
                'matched_sources',
                'confidence_score',
            ]);

        if ($source !== null) {
            $query->where('source_name', $source);
        }
        if ($status !== null) {
            $query->where('status', $status);
        }

        $groups = $query->orderBy('id')->get()->groupBy('canonical_key');
        $signalsUpdated = 0;
        $duplicatesMerged = 0;

        foreach ($groups as $group) {
            $mergedSources = [];
            foreach ($group as $candidate) {
                $mergedSources[] = (string) $candidate->source_name;
                if (is_array($candidate->matched_sources)) {
                    $mergedSources = array_merge($mergedSources, $candidate->matched_sources);
                }
            }

            $normalizedSources = $this->normalizeMatchedSources($mergedSources);
            $resolvedScore = $normalizedSources !== null && count($normalizedSources) >= 2 ? 1.0 : 0.7;

            foreach ($group as $candidate) {
                $currentSources = $this->normalizeMatchedSources(
                    is_array($candidate->matched_sources) ? $candidate->matched_sources : []
                );
                $currentScore = $candidate->confidence_score !== null ? round((float) $candidate->confidence_score, 2) : null;
                $needsUpdate = $currentSources !== $normalizedSources || $currentScore !== $resolvedScore;

                if (! $needsUpdate) {
                    continue;
                }

                $candidate->matched_sources = $normalizedSources;
                $candidate->confidence_score = $resolvedScore;
                $candidate->save();
                $signalsUpdated++;
            }

            if (! $mergePendingDuplicates) {
                continue;
            }

            $pending = $group
                ->filter(static fn (EventCandidate $candidate): bool => $candidate->status === EventCandidate::STATUS_PENDING)
                ->sortByDesc(static fn (EventCandidate $candidate): float => $candidate->confidence_score !== null ? (float) $candidate->confidence_score : 0.0)
                ->sortByDesc('id')
                ->values();

            if ($pending->count() <= 1) {
                continue;
            }

            $keepId = (int) $pending->first()->id;
            foreach ($pending as $candidate) {
                if ((int) $candidate->id === $keepId) {
                    continue;
                }

                $candidate->status = EventCandidate::STATUS_DUPLICATE;
                $candidate->save();
                $duplicatesMerged++;
            }
        }

        return [$signalsUpdated, $duplicatesMerged];
    }

    private function normalizeCanonical(?string $value): ?string
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
}
