<?php

namespace App\Services\Events;

use App\Models\Event;
use App\Models\EventCandidate;
use Illuminate\Database\Eloquent\Builder;

class PublishedEventQuery
{
    public function base(): Builder
    {
        return Event::query()
            ->where('visibility', 1)
            ->published()
            ->where(function (Builder $sub): void {
                $sub->where('source_name', 'manual')
                    ->orWhereExists(function ($q): void {
                        $q->selectRaw('1')
                            ->from('event_candidates')
                            ->whereColumn('event_candidates.published_event_id', 'events.id')
                            ->where('event_candidates.status', EventCandidate::STATUS_APPROVED);
                    });
            });
    }
}
