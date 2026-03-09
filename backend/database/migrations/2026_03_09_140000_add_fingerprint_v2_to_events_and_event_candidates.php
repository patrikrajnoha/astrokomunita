<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_candidates', function (Blueprint $table): void {
            if (! Schema::hasColumn('event_candidates', 'fingerprint_v2')) {
                $table->string('fingerprint_v2', 64)->nullable()->after('source_hash');
                $table->index('fingerprint_v2', 'event_candidates_fingerprint_v2_idx');
            }
        });

        Schema::table('events', function (Blueprint $table): void {
            if (! Schema::hasColumn('events', 'fingerprint_v2')) {
                $table->string('fingerprint_v2', 64)->nullable()->after('source_hash');
                $table->index('fingerprint_v2', 'events_fingerprint_v2_idx');
            }
        });

        $this->backfillEventCandidateFingerprints();
        $this->backfillEventFingerprints();
    }

    public function down(): void
    {
        Schema::table('event_candidates', function (Blueprint $table): void {
            if (Schema::hasColumn('event_candidates', 'fingerprint_v2')) {
                $table->dropIndex('event_candidates_fingerprint_v2_idx');
                $table->dropColumn('fingerprint_v2');
            }
        });

        Schema::table('events', function (Blueprint $table): void {
            if (Schema::hasColumn('events', 'fingerprint_v2')) {
                $table->dropIndex('events_fingerprint_v2_idx');
                $table->dropColumn('fingerprint_v2');
            }
        });
    }

    private function backfillEventCandidateFingerprints(): void
    {
        DB::table('event_candidates')
            ->select(['id', 'canonical_key', 'type', 'start_at', 'max_at', 'title', 'source_hash'])
            ->whereNull('fingerprint_v2')
            ->orderBy('id')
            ->chunkById(200, function ($rows): void {
                foreach ($rows as $row) {
                    $fingerprint = $this->buildFingerprint(
                        canonicalKey: is_string($row->canonical_key) ? $row->canonical_key : null,
                        type: is_string($row->type) ? $row->type : null,
                        startAt: $row->start_at,
                        maxAt: $row->max_at,
                        title: is_string($row->title) ? $row->title : null,
                        sourceHash: is_string($row->source_hash) ? $row->source_hash : null,
                    );

                    if ($fingerprint === null) {
                        continue;
                    }

                    DB::table('event_candidates')
                        ->where('id', (int) $row->id)
                        ->update(['fingerprint_v2' => $fingerprint]);
                }
            });
    }

    private function backfillEventFingerprints(): void
    {
        DB::table('events')
            ->select(['id', 'canonical_key', 'type', 'start_at', 'max_at', 'title', 'source_hash'])
            ->whereNull('fingerprint_v2')
            ->orderBy('id')
            ->chunkById(200, function ($rows): void {
                foreach ($rows as $row) {
                    $fingerprint = $this->buildFingerprint(
                        canonicalKey: is_string($row->canonical_key) ? $row->canonical_key : null,
                        type: is_string($row->type) ? $row->type : null,
                        startAt: $row->start_at,
                        maxAt: $row->max_at,
                        title: is_string($row->title) ? $row->title : null,
                        sourceHash: is_string($row->source_hash) ? $row->source_hash : null,
                    );

                    if ($fingerprint === null) {
                        continue;
                    }

                    DB::table('events')
                        ->where('id', (int) $row->id)
                        ->update(['fingerprint_v2' => $fingerprint]);
                }
            });
    }

    private function buildFingerprint(
        ?string $canonicalKey,
        ?string $type,
        mixed $startAt,
        mixed $maxAt,
        ?string $title,
        ?string $sourceHash,
    ): ?string {
        $parts = [];

        $normalizedCanonical = $this->normalizeText($canonicalKey);
        if ($normalizedCanonical !== null) {
            $parts[] = 'ck:'.$normalizedCanonical;
        }

        $normalizedType = $this->normalizeText($type);
        if ($normalizedType !== null) {
            $parts[] = 'tp:'.$normalizedType;
        }

        $date = $this->resolveDateString($startAt, $maxAt);
        if ($date !== null) {
            $parts[] = 'dt:'.$date;
        }

        $normalizedTitle = $this->normalizeText($title);
        if ($normalizedTitle !== null) {
            $parts[] = 'ttl:'.$normalizedTitle;
        }

        if ($parts !== []) {
            return hash('sha256', implode('|', $parts));
        }

        $hash = trim((string) $sourceHash);

        return $hash !== '' ? $hash : null;
    }

    private function normalizeText(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim($value);
        if ($normalized === '') {
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

    private function resolveDateString(mixed $startAt, mixed $maxAt): ?string
    {
        foreach ([$startAt, $maxAt] as $value) {
            if ($value instanceof DateTimeInterface) {
                return $value->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d');
            }

            $raw = trim((string) $value);
            if ($raw === '') {
                continue;
            }

            $timestamp = strtotime($raw);
            if ($timestamp === false) {
                continue;
            }

            return gmdate('Y-m-d', $timestamp);
        }

        return null;
    }
};
