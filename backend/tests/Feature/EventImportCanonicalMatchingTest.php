<?php

namespace Tests\Feature;

use App\Models\EventCandidate;
use App\Jobs\TranslateEventCandidateJob;
use App\Services\Crawlers\CandidateItem;
use App\Services\EventImport\EventImportService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class EventImportCanonicalMatchingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('translation.events.enabled', false);
    }

    public function test_import_computes_canonical_key_and_default_confidence_for_single_source(): void
    {
        /** @var EventImportService $service */
        $service = app(EventImportService::class);

        $item = $this->makeCandidateItem(
            title: 'Lyrids (LYR)',
            sourceUid: 'astropixels-lyrids-2026',
            sourceUrl: 'https://astropixels.test/lyrids'
        );

        $service->importFromCandidateItems(
            sourceName: 'astropixels',
            sourceUrl: 'https://astropixels.test',
            items: [$item]
        );

        $candidate = EventCandidate::query()->firstOrFail();
        $this->assertSame('meteor shower|2026-04-22|lyrids lyr', $candidate->canonical_key);
        $this->assertSame('0.70', (string) $candidate->confidence_score);
        $this->assertSame(['astropixels'], $candidate->matched_sources);
    }

    public function test_import_merges_matched_sources_and_sets_high_confidence_for_cross_source_match(): void
    {
        /** @var EventImportService $service */
        $service = app(EventImportService::class);

        $astropixelsItem = $this->makeCandidateItem(
            title: 'Lyrids (LYR)',
            sourceUid: 'astropixels-lyrids-2026',
            sourceUrl: 'https://astropixels.test/lyrids'
        );

        $imoItem = $this->makeCandidateItem(
            title: 'Lyrids (LYR)',
            sourceUid: 'imo-lyrids-2026',
            sourceUrl: 'https://imo.test/lyrids'
        );

        $service->importFromCandidateItems(
            sourceName: 'astropixels',
            sourceUrl: 'https://astropixels.test',
            items: [$astropixelsItem]
        );
        $service->importFromCandidateItems(
            sourceName: 'imo',
            sourceUrl: 'https://imo.test',
            items: [$imoItem]
        );

        $candidates = EventCandidate::query()
            ->orderBy('source_name')
            ->get();

        $this->assertCount(2, $candidates);
        $this->assertSame($candidates[0]->canonical_key, $candidates[1]->canonical_key);
        $this->assertSame(['astropixels', 'imo'], $candidates[0]->matched_sources);
        $this->assertSame(['astropixels', 'imo'], $candidates[1]->matched_sources);
        $this->assertSame('1.00', (string) $candidates[0]->confidence_score);
        $this->assertSame('1.00', (string) $candidates[1]->confidence_score);
    }

    public function test_import_dispatches_translation_job_even_when_queue_driver_is_sync(): void
    {
        config()->set('translation.events.enabled', true);
        config()->set('translation.allow_sync_queue', true);
        config()->set('queue.default', 'sync');
        Bus::fake();

        /** @var EventImportService $service */
        $service = app(EventImportService::class);

        $item = $this->makeCandidateItem(
            title: 'Lyrids (LYR)',
            sourceUid: 'astropixels-lyrids-2026',
            sourceUrl: 'https://astropixels.test/lyrids'
        );

        $service->importFromCandidateItems(
            sourceName: 'astropixels',
            sourceUrl: 'https://astropixels.test',
            items: [$item]
        );

        Bus::assertDispatched(TranslateEventCandidateJob::class, function (TranslateEventCandidateJob $job): bool {
            return $job->candidateId > 0 && $job->force === false;
        });
    }

    private function makeCandidateItem(string $title, string $sourceUid, string $sourceUrl): CandidateItem
    {
        return new CandidateItem(
            title: $title,
            startsAtUtc: CarbonImmutable::parse('2026-04-22 20:00:00', 'UTC'),
            endsAtUtc: null,
            description: 'Meteor shower peak.',
            sourceUrl: $sourceUrl,
            externalId: $sourceUid,
            rawPayload: ['test' => true],
            eventType: 'meteor_shower'
        );
    }
}
