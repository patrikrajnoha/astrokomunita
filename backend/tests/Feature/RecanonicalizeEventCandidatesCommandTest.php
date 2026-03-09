<?php

namespace Tests\Feature;

use App\Models\EventCandidate;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecanonicalizeEventCandidatesCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_recomputes_canonical_key_and_merges_pending_duplicates(): void
    {
        $astropixels = EventCandidate::query()->create([
            'source_name' => 'astropixels',
            'source_url' => 'https://astropixels.test/almanac2026cet.html',
            'source_uid' => 'astropixels-2026-12-30-last-quarter',
            'external_id' => 'astropixels-2026-12-30-last-quarter',
            'stable_key' => 'astropixels-2026-12-30-last-quarter',
            'source_hash' => hash('sha256', 'astropixels-2026-12-30-last-quarter'),
            'title' => 'Last Quarter Moon',
            'description' => 'AstroPixels phase row.',
            'type' => 'other',
            'raw_type' => 'moon_phase',
            'canonical_key' => 'other|2026-12-30|last quarter moon',
            'matched_sources' => ['astropixels'],
            'confidence_score' => 0.70,
            'start_at' => CarbonImmutable::parse('2026-12-30 19:59:00', 'UTC'),
            'max_at' => CarbonImmutable::parse('2026-12-30 19:59:00', 'UTC'),
            'status' => EventCandidate::STATUS_PENDING,
            'translation_status' => EventCandidate::TRANSLATION_PENDING,
            'raw_payload' => '{}',
        ]);

        $nasaWts = EventCandidate::query()->create([
            'source_name' => 'nasa_wts',
            'source_url' => 'https://aa.usno.navy.mil/api/moon/phases/year?year=2026',
            'source_uid' => 'usno-2026-12-30-last-quarter',
            'external_id' => 'usno-2026-12-30-last-quarter',
            'stable_key' => 'usno-2026-12-30-last-quarter',
            'source_hash' => hash('sha256', 'usno-2026-12-30-last-quarter'),
            'title' => 'Last Quarter Moon',
            'description' => 'USNO phase row.',
            'type' => 'observation_window',
            'raw_type' => 'observation_window',
            'canonical_key' => 'observation window|2026-12-30|last quarter moon',
            'matched_sources' => ['nasa_wts'],
            'confidence_score' => 0.70,
            'start_at' => CarbonImmutable::parse('2026-12-30 19:59:00', 'UTC'),
            'max_at' => CarbonImmutable::parse('2026-12-30 19:59:00', 'UTC'),
            'status' => EventCandidate::STATUS_PENDING,
            'translation_status' => EventCandidate::TRANSLATION_PENDING,
            'raw_payload' => '{}',
        ]);

        $this->artisan('events:candidates:recanonicalize', [
            '--merge-pending-duplicates' => true,
        ])->assertExitCode(0);

        $astropixels->refresh();
        $nasaWts->refresh();

        $this->assertSame('observation_window', $astropixels->type);
        $this->assertSame('observation window|2026-12-30|last quarter moon', $astropixels->canonical_key);
        $this->assertSame('observation window|2026-12-30|last quarter moon', $nasaWts->canonical_key);

        $this->assertContains($astropixels->status, [EventCandidate::STATUS_PENDING, EventCandidate::STATUS_DUPLICATE]);
        $this->assertContains($nasaWts->status, [EventCandidate::STATUS_PENDING, EventCandidate::STATUS_DUPLICATE]);
        $this->assertNotSame($astropixels->status, $nasaWts->status);

        $keeper = EventCandidate::query()->where('status', EventCandidate::STATUS_PENDING)->firstOrFail();
        $this->assertSame(['astropixels', 'nasa_wts'], $keeper->matched_sources);
        $this->assertSame('1.00', (string) $keeper->confidence_score);
    }
}
