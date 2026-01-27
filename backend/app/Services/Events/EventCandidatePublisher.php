<?php

namespace App\Services\Events;

use App\Models\Event;
use App\Models\EventCandidate;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class EventCandidatePublisher
{
    /**
     * Schváli kandidáta a vytvorí z neho ostrú udalosť (Event).
     */
    public function approve(EventCandidate $candidate, int $reviewerUserId): Event
    {
        return DB::transaction(function () use ($candidate, $reviewerUserId) {

            $candidate->refresh();

            // 1) Robustná kontrola schváleného stavu (Idempotencia + Oprava nekonzistencie)
            if ($candidate->status === 'approved') {
                if ($candidate->published_event_id) {
                    return Event::findOrFail($candidate->published_event_id);
                }

                // Fallback: Ak je approved, ale chýba ID, skúsime nájsť event podľa zdroja
                $existing = Event::query()
                    ->where('source_name', $candidate->source_name)
                    ->where('source_uid', $candidate->source_uid)
                    ->first();

                if ($existing) {
                    $this->updateCandidateAsApproved($candidate, $existing->id, $reviewerUserId);
                    return $existing;
                }

                // Ak je status approved, ale event neexistuje nikde, dovolíme pokračovať k vytvoreniu nového
            }

            // 2) Stavový konflikt: schvaľovať sa dá iba pending (alebo vyššie ošetrený nekonzistentný approved)
            if ($candidate->status !== 'pending' && $candidate->status !== 'approved') {
                throw new RuntimeException("Candidate is not in a valid state for approval (current: {$candidate->status}).");
            }

            $event = new Event();

            // Základné údaje
            $event->title = $candidate->title;
            $event->description = $candidate->description;
            $event->type = $candidate->type ?? 'other';

            // Časové údaje
            $event->start_at = $candidate->start_at;
            $event->end_at   = $candidate->end_at;

            // Rozšírené údaje
            $event->max_at = $candidate->max_at;
            $event->short = $candidate->short;
            $event->visibility = $candidate->visibility ?? config('events.default_visibility', 1);

            // Traceability
            $event->source_name = $candidate->source_name;
            $event->source_uid  = $candidate->source_uid;
            $event->source_hash = $candidate->source_hash;

            try {
                $event->save();
            } catch (QueryException $e) {
                // Zachytenie race condition: MySQL duplicate key = SQLSTATE 23000
                if (($e->errorInfo[0] ?? null) === '23000') {
                    $existing = Event::query()
                        ->where('source_name', $candidate->source_name)
                        ->where('source_uid', $candidate->source_uid)
                        ->first();

                    if ($existing) {
                        $this->updateCandidateAsApproved($candidate, $existing->id, $reviewerUserId);
                        return $existing;
                    }
                }
                throw $e;
            }

            // 3) Štandardná aktualizácia kandidáta
            $this->updateCandidateAsApproved($candidate, $event->id, $reviewerUserId);

            return $event;
        });
    }

    /**
     * Pomocná metóda pre označenie kandidáta za schváleného.
     */
    private function updateCandidateAsApproved(EventCandidate $candidate, int $eventId, int $reviewerUserId): void
    {
        // Refresh pred zápisom pre prípad race condition
        $candidate->refresh();
        
        $candidate->status = 'approved';
        $candidate->reviewed_by = $reviewerUserId;
        $candidate->reviewed_at = CarbonImmutable::now();
        $candidate->published_event_id = $eventId;
        $candidate->reject_reason = null;
        $candidate->save();
    }

    /**
     * Zamietne kandidáta s uvedením dôvodu.
     */
    public function reject(EventCandidate $candidate, int $reviewerUserId, string $reason): void
    {
        DB::transaction(function () use ($candidate, $reviewerUserId, $reason) {

            $candidate->refresh();

            if ($candidate->status !== 'pending') {
                throw new RuntimeException("Candidate is not pending (current: {$candidate->status}).");
            }

            $candidate->status = 'rejected';
            $candidate->reviewed_by = $reviewerUserId;
            $candidate->reviewed_at = CarbonImmutable::now();
            $candidate->reject_reason = $reason;
            $candidate->published_event_id = null;

            $candidate->save();
        });
    }
}