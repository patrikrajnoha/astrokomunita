<?php

namespace Tests\Unit\Events;

use App\Models\Event;
use App\Models\EventCandidate;
use App\Services\Events\PublishedEventQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublishedEventQueryTest extends TestCase
{
    use RefreshDatabase;

    public function test_base_returns_builder_and_applies_published_gate_filters(): void
    {
        $manualVisible = $this->createEvent('Manual visible', 'manual', 'manual-visible', 1);
        $this->createEvent('Manual hidden', 'manual', 'manual-hidden', 0);

        $approvedCandidateEvent = $this->createEvent('Approved candidate', 'astropixels', 'approved-candidate', 1);
        EventCandidate::query()->create([
            'source_name' => 'astropixels',
            'source_uid' => 'approved-candidate',
            'source_hash' => sha1('approved-candidate'),
            'title' => 'Approved candidate row',
            'type' => 'other',
            'max_at' => now()->utc(),
            'start_at' => now()->utc(),
            'status' => EventCandidate::STATUS_APPROVED,
            'published_event_id' => $approvedCandidateEvent->id,
        ]);

        $pendingCandidateEvent = $this->createEvent('Pending candidate', 'astropixels', 'pending-candidate', 1);
        EventCandidate::query()->create([
            'source_name' => 'astropixels',
            'source_uid' => 'pending-candidate',
            'source_hash' => sha1('pending-candidate'),
            'title' => 'Pending candidate row',
            'type' => 'other',
            'max_at' => now()->utc(),
            'start_at' => now()->utc(),
            'status' => EventCandidate::STATUS_PENDING,
            'published_event_id' => $pendingCandidateEvent->id,
        ]);

        $query = app(PublishedEventQuery::class)->base();

        $this->assertInstanceOf(Builder::class, $query);

        $ids = $query->pluck('id')->all();

        $this->assertContains($manualVisible->id, $ids);
        $this->assertContains($approvedCandidateEvent->id, $ids);
        $this->assertNotContains($pendingCandidateEvent->id, $ids);
    }

    private function createEvent(string $title, string $sourceName, string $sourceUid, int $visibility): Event
    {
        return Event::query()->create([
            'title' => $title,
            'type' => 'other',
            'start_at' => now()->utc(),
            'end_at' => now()->utc()->addHour(),
            'max_at' => now()->utc(),
            'visibility' => $visibility,
            'source_name' => $sourceName,
            'source_uid' => $sourceUid,
            'source_hash' => sha1($sourceName . '|' . $sourceUid),
        ]);
    }
}
