<?php

namespace Tests\Feature;

use App\Jobs\TranslateEventCandidateJob;
use App\Models\EventCandidate;
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
        $this->assertSame('meteor shower|2026-04-22|lyrids', $candidate->canonical_key);
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
        $secondResult = $service->importFromCandidateItems(
            sourceName: 'imo',
            sourceUrl: 'https://imo.test',
            items: [$imoItem]
        );

        $this->assertSame(0, $secondResult->imported);
        $this->assertSame(0, $secondResult->updated);
        $this->assertSame(1, $secondResult->duplicates);

        $candidate = EventCandidate::query()->firstOrFail();
        $this->assertSame(['astropixels', 'imo'], $candidate->matched_sources);
        $this->assertSame('1.00', (string) $candidate->confidence_score);
    }

    public function test_import_deduplicates_moon_phase_candidates_across_sources_with_different_raw_types(): void
    {
        /** @var EventImportService $service */
        $service = app(EventImportService::class);

        $astropixelsMoon = new CandidateItem(
            title: 'Last Quarter Moon',
            startsAtUtc: CarbonImmutable::parse('2026-12-30 19:59:00', 'UTC'),
            endsAtUtc: null,
            description: 'AstroPixels lunar phase row.',
            sourceUrl: 'https://astropixels.test/almanac',
            externalId: 'ap-last-quarter-2026-12-30',
            rawPayload: ['source' => 'astropixels'],
            eventType: 'moon_phase'
        );

        $nasaWtsMoon = new CandidateItem(
            title: 'Last Quarter Moon',
            startsAtUtc: CarbonImmutable::parse('2026-12-30 19:59:00', 'UTC'),
            endsAtUtc: null,
            description: 'USNO lunar phase row.',
            sourceUrl: 'https://aa.usno.navy.mil/api/moon/phases/year?year=2026',
            externalId: 'usno-last-quarter-2026-12-30',
            rawPayload: ['source' => 'nasa_wts'],
            eventType: 'observation_window'
        );

        $service->importFromCandidateItems(
            sourceName: 'astropixels',
            sourceUrl: 'https://astropixels.test',
            items: [$astropixelsMoon]
        );
        $secondResult = $service->importFromCandidateItems(
            sourceName: 'nasa_wts',
            sourceUrl: 'https://aa.usno.navy.mil/api/moon/phases/year',
            items: [$nasaWtsMoon]
        );

        $this->assertSame(0, $secondResult->imported);
        $this->assertSame(0, $secondResult->updated);
        $this->assertSame(1, $secondResult->duplicates);
        $this->assertSame(1, EventCandidate::query()->count());

        $candidate = EventCandidate::query()->firstOrFail();
        $this->assertSame('observation_window', $candidate->type);
        $this->assertSame(['astropixels', 'nasa_wts'], $candidate->matched_sources);
        $this->assertSame('1.00', (string) $candidate->confidence_score);
    }

    public function test_import_deduplicates_meteor_shower_candidates_with_different_title_variants(): void
    {
        /** @var EventImportService $service */
        $service = app(EventImportService::class);

        $astropixels = new CandidateItem(
            title: 'Meteorický roj Geminid',
            startsAtUtc: CarbonImmutable::parse('2026-12-14 14:00:00', 'UTC'),
            endsAtUtc: null,
            description: 'AstroPixels row.',
            sourceUrl: 'https://astropixels.test/almanac',
            externalId: 'ap-geminids-2026',
            rawPayload: ['source' => 'astropixels'],
            eventType: 'meteor_shower'
        );

        $imo = new CandidateItem(
            title: 'Geminidy (GEM)',
            startsAtUtc: CarbonImmutable::parse('2026-12-14 01:00:00', 'UTC'),
            endsAtUtc: null,
            description: 'IMO row.',
            sourceUrl: 'https://imo.test/calendar',
            externalId: 'imo-gem-2026',
            rawPayload: ['source' => 'imo'],
            eventType: 'meteor_shower'
        );

        $service->importFromCandidateItems(
            sourceName: 'astropixels',
            sourceUrl: 'https://astropixels.test',
            items: [$astropixels]
        );
        $secondResult = $service->importFromCandidateItems(
            sourceName: 'imo',
            sourceUrl: 'https://imo.test',
            items: [$imo]
        );

        $this->assertSame(0, $secondResult->imported);
        $this->assertSame(0, $secondResult->updated);
        $this->assertSame(1, $secondResult->duplicates);
        $this->assertSame(1, EventCandidate::query()->count());

        $candidate = EventCandidate::query()->firstOrFail();
        $this->assertSame('meteor shower|2026-12-14|geminids', $candidate->canonical_key);
        $this->assertSame(['astropixels', 'imo'], $candidate->matched_sources);
        $this->assertSame('1.00', (string) $candidate->confidence_score);
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

    public function test_import_updates_existing_candidate_when_source_uid_changes_but_fingerprint_matches(): void
    {
        /** @var EventImportService $service */
        $service = app(EventImportService::class);

        $first = $this->makeCandidateItem(
            title: 'Lyrids (LYR)',
            sourceUid: 'lyrids-v1',
            sourceUrl: 'https://astropixels.test/lyrids-v1'
        );

        $second = $this->makeCandidateItem(
            title: 'Lyrids (LYR)',
            sourceUid: 'lyrids-v2',
            sourceUrl: 'https://astropixels.test/lyrids-v2'
        );

        $service->importFromCandidateItems(
            sourceName: 'astropixels',
            sourceUrl: 'https://astropixels.test',
            items: [$first]
        );

        $result = $service->importFromCandidateItems(
            sourceName: 'astropixels',
            sourceUrl: 'https://astropixels.test',
            items: [$second]
        );

        $this->assertSame(0, $result->imported);
        $this->assertSame(1, $result->updated);
        $this->assertSame(0, $result->duplicates);

        $this->assertSame(1, EventCandidate::query()->count());

        $candidate = EventCandidate::query()->firstOrFail();
        $this->assertSame('lyrids-v2', $candidate->source_uid);
        $this->assertSame('lyrids-v2', $candidate->external_id);
        $this->assertSame('lyrids-v2', $candidate->stable_key);
        $this->assertNotNull($candidate->fingerprint_v2);
        $this->assertSame(64, strlen((string) $candidate->fingerprint_v2));
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
