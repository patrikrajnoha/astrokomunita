<?php

namespace App\Services\Translation;

use App\Models\EventCandidate;
use Illuminate\Database\Eloquent\Builder;

class EventTranslationArtifactDetector
{
    /**
     * @var array<int,string>
     */
    private const PATTERNS = [
        '%conjunction%',
        '%with slnko%',
        '%with mesiac%',
        '%quarter moon%',
        '%kvartn% moon%',
        '%novi moon%',
        '%full moon%',
        '%new moon%',
        '%v konflikte so slnkom%',
        '%na vrchole%',
        '%odrazeferora%',
    ];

    public function suspiciousCount(): int
    {
        return $this->baseQuery()
            ->distinct()
            ->count('event_candidates.id');
    }

    /**
     * @return array<int,int>
     */
    public function suspiciousCandidateIds(int $limit = 0): array
    {
        $query = $this->baseQuery()->orderBy('event_candidates.id');

        if ($limit > 0) {
            $query->limit($limit);
        }

        return $query->pluck('event_candidates.id')
            ->map(static fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<int,array{candidate_id:int,event_id:?int,source_title:string,translated_title:string,event_title:string}>
     */
    public function suspiciousSamples(int $limit = 20): array
    {
        $query = $this->baseQuery()
            ->select([
                'event_candidates.id as candidate_id',
                'event_candidates.published_event_id as event_id',
                'event_candidates.title as source_title',
                'event_candidates.translated_title as translated_title',
                'events.title as event_title',
            ])
            ->orderBy('event_candidates.id');

        if ($limit > 0) {
            $query->limit($limit);
        }

        return $query->get()
            ->map(static fn ($row): array => [
                'candidate_id' => (int) $row->candidate_id,
                'event_id' => $row->event_id !== null ? (int) $row->event_id : null,
                'source_title' => trim((string) ($row->source_title ?? '')),
                'translated_title' => trim((string) ($row->translated_title ?? '')),
                'event_title' => trim((string) ($row->event_title ?? '')),
            ])
            ->values()
            ->all();
    }

    private function baseQuery(): Builder
    {
        $query = EventCandidate::query()
            ->select('event_candidates.id')
            ->leftJoin('events', 'events.id', '=', 'event_candidates.published_event_id')
            ->where('event_candidates.status', EventCandidate::STATUS_APPROVED)
            ->whereNotNull('event_candidates.published_event_id');

        $this->applySuspiciousConditions($query);

        return $query;
    }

    private function applySuspiciousConditions(Builder $query): void
    {
        $query->where(function ($outer): void {
            foreach (self::PATTERNS as $pattern) {
                $outer->orWhereRaw('LOWER(COALESCE(event_candidates.translated_title, event_candidates.title, \'\')) LIKE ?', [$pattern])
                    ->orWhereRaw('LOWER(COALESCE(event_candidates.translated_description, event_candidates.description, \'\')) LIKE ?', [$pattern])
                    ->orWhereRaw('LOWER(COALESCE(events.title, \'\')) LIKE ?', [$pattern])
                    ->orWhereRaw('LOWER(COALESCE(events.description, \'\')) LIKE ?', [$pattern]);
            }

            $outer->orWhereRaw('COALESCE(event_candidates.translated_title, event_candidates.title, \'\') LIKE ?', ['%?%'])
                ->orWhereRaw('COALESCE(events.title, \'\') LIKE ?', ['%?%'])
                ->orWhere(function ($q): void {
                    $q->whereRaw('LOWER(COALESCE(event_candidates.title, \'\')) LIKE ?', ['% in conjunction with sun%'])
                        ->whereRaw('LOWER(COALESCE(event_candidates.translated_title, \'\')) NOT LIKE ?', ['% v konjunkcii so slnkom%']);
                })
                ->orWhere(function ($q): void {
                    $q->whereRaw('LOWER(COALESCE(event_candidates.title, \'\')) LIKE ?', ['% at superior conjunction%'])
                        ->whereRaw('LOWER(COALESCE(event_candidates.translated_title, \'\')) NOT LIKE ?', ['% v hornej konjunkcii%']);
                })
                ->orWhere(function ($q): void {
                    $q->whereRaw('LOWER(COALESCE(event_candidates.title, \'\')) LIKE ?', ['% at inferior conjunction%'])
                        ->whereRaw('LOWER(COALESCE(event_candidates.translated_title, \'\')) NOT LIKE ?', ['% v dolnej konjunkcii%']);
                })
                ->orWhere(function ($q): void {
                    $q->whereRaw('LOWER(COALESCE(event_candidates.title, \'\')) LIKE ?', ['% first quarter moon%'])
                        ->where(function ($sub): void {
                            $sub->whereRaw('LOWER(COALESCE(event_candidates.translated_title, \'\')) LIKE ?', ['%moon%'])
                                ->orWhereRaw('LOWER(COALESCE(event_candidates.translated_title, \'\')) LIKE ?', ['%quarter%'])
                                ->orWhereRaw('LOWER(COALESCE(event_candidates.translated_title, \'\')) LIKE ?', ['%kvartn%']);
                        });
                })
                ->orWhere(function ($q): void {
                    $q->whereRaw('LOWER(COALESCE(event_candidates.title, \'\')) LIKE ?', ['% last quarter moon%'])
                        ->where(function ($sub): void {
                            $sub->whereRaw('LOWER(COALESCE(event_candidates.translated_title, \'\')) LIKE ?', ['%moon%'])
                                ->orWhereRaw('LOWER(COALESCE(event_candidates.translated_title, \'\')) LIKE ?', ['%quarter%'])
                                ->orWhereRaw('LOWER(COALESCE(event_candidates.translated_title, \'\')) LIKE ?', ['%kvartn%']);
                        });
                })
                ->orWhere(function ($q): void {
                    $q->whereRaw('LOWER(COALESCE(event_candidates.title, \'\')) LIKE ?', ['% new moon%'])
                        ->whereRaw('LOWER(COALESCE(event_candidates.translated_title, \'\')) LIKE ?', ['%moon%']);
                })
                ->orWhere(function ($q): void {
                    $q->whereRaw('LOWER(COALESCE(event_candidates.title, \'\')) LIKE ?', ['% full moon%'])
                        ->whereRaw('LOWER(COALESCE(event_candidates.translated_title, \'\')) LIKE ?', ['%moon%']);
                });
        });
    }
}
