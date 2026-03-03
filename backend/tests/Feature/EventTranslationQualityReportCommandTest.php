<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventCandidate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventTranslationQualityReportCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_reports_no_findings_for_clean_translations(): void
    {
        $event = Event::query()->create([
            'title' => 'Saturn v konjunkcii so Slnkom',
            'type' => 'planetary_event',
            'start_at' => now(),
            'max_at' => now(),
            'short' => 'Saturn v konjunkcii so Slnkom',
            'description' => 'Saturn v konjunkcii so Slnkom nastane 25.03.2026.',
            'source_name' => 'astropixels',
            'source_uid' => 'event-clean-1',
            'source_hash' => hash('sha256', 'event-clean-1'),
        ]);

        EventCandidate::query()->create([
            'source_name' => 'astropixels',
            'source_url' => 'https://astropixels.test/almanac2026cet.html',
            'source_uid' => 'candidate-clean-1',
            'external_id' => 'candidate-clean-1',
            'stable_key' => 'candidate-clean-1',
            'source_hash' => hash('sha256', 'candidate-clean-1'),
            'title' => 'Saturn in Conjunction with Sun',
            'original_title' => 'Saturn in Conjunction with Sun',
            'translated_title' => 'Saturn v konjunkcii so Slnkom',
            'description' => 'Saturn in Conjunction with Sun occurs on 25.03.2026.',
            'original_description' => 'Saturn in Conjunction with Sun occurs on 25.03.2026.',
            'translated_description' => 'Saturn v konjunkcii so Slnkom nastane 25.03.2026.',
            'type' => 'planetary_event',
            'max_at' => now(),
            'start_at' => now(),
            'short' => 'Saturn v konjunkcii so Slnkom',
            'raw_payload' => '{}',
            'status' => EventCandidate::STATUS_APPROVED,
            'translation_status' => EventCandidate::TRANSLATION_DONE,
            'published_event_id' => $event->id,
        ]);

        $this->artisan('events:translation-quality-report')
            ->expectsOutputToContain('no suspicious artifacts found')
            ->assertExitCode(0);
    }

    public function test_it_reports_findings_and_can_fail_on_findings(): void
    {
        $event = Event::query()->create([
            'title' => 'Jupiter v konflikte so slnkom',
            'type' => 'planetary_event',
            'start_at' => now(),
            'max_at' => now(),
            'short' => 'Jupiter v konflikte so slnkom',
            'description' => 'Jupiter v konflikte so slnkom nastane 29.10.2026.',
            'source_name' => 'astropixels',
            'source_uid' => 'event-bad-1',
            'source_hash' => hash('sha256', 'event-bad-1'),
        ]);

        $candidate = EventCandidate::query()->create([
            'source_name' => 'astropixels',
            'source_url' => 'https://astropixels.test/almanac2026cet.html',
            'source_uid' => 'candidate-bad-1',
            'external_id' => 'candidate-bad-1',
            'stable_key' => 'candidate-bad-1',
            'source_hash' => hash('sha256', 'candidate-bad-1'),
            'title' => 'Jupiter in Conjunction with Sun',
            'original_title' => 'Jupiter in Conjunction with Sun',
            'translated_title' => 'Jupiter v konflikte so slnkom',
            'description' => 'Jupiter in Conjunction with Sun occurs on 29.10.2026.',
            'original_description' => 'Jupiter in Conjunction with Sun occurs on 29.10.2026.',
            'translated_description' => 'Jupiter v konflikte so slnkom nastane 29.10.2026.',
            'type' => 'planetary_event',
            'max_at' => now(),
            'start_at' => now(),
            'short' => 'Jupiter v konflikte so slnkom',
            'raw_payload' => '{}',
            'status' => EventCandidate::STATUS_APPROVED,
            'translation_status' => EventCandidate::TRANSLATION_DONE,
            'published_event_id' => $event->id,
        ]);

        $this->artisan('events:translation-quality-report --sample=5')
            ->expectsOutputToContain('suspicious approved candidates found: 1')
            ->expectsOutputToContain((string) $candidate->id)
            ->assertExitCode(0);

        $this->artisan('events:translation-quality-report --sample=5 --fail-on-findings')
            ->expectsOutputToContain('suspicious approved candidates found: 1')
            ->assertExitCode(1);
    }

    public function test_it_reports_mixed_quarter_moon_findings(): void
    {
        $event = Event::query()->create([
            'title' => "POSLEDN\u{0130} KVARTN\u{0130} MOON",
            'type' => 'moon_phase',
            'start_at' => now(),
            'max_at' => now(),
            'short' => "POSLEDN\u{0130} KVARTN\u{0130} MOON",
            'description' => "11 10:39 POSLEDN\u{0130} QUARTER MOON",
            'source_name' => 'astropixels',
            'source_uid' => 'event-bad-quarter-1',
            'source_hash' => hash('sha256', 'event-bad-quarter-1'),
        ]);

        $candidate = EventCandidate::query()->create([
            'source_name' => 'astropixels',
            'source_url' => 'https://astropixels.test/almanac2026cet.html',
            'source_uid' => 'candidate-bad-quarter-1',
            'external_id' => 'candidate-bad-quarter-1',
            'stable_key' => 'candidate-bad-quarter-1',
            'source_hash' => hash('sha256', 'candidate-bad-quarter-1'),
            'title' => 'LAST QUARTER MOON',
            'original_title' => 'LAST QUARTER MOON',
            'translated_title' => "POSLEDN\u{0130} KVARTN\u{0130} MOON",
            'description' => 'LAST QUARTER MOON occurs on 11.03.2026.',
            'original_description' => 'LAST QUARTER MOON occurs on 11.03.2026.',
            'translated_description' => "11 10:39 POSLEDN\u{0130} QUARTER MOON",
            'type' => 'moon_phase',
            'max_at' => now(),
            'start_at' => now(),
            'short' => "POSLEDN\u{0130} KVARTN\u{0130} MOON",
            'raw_payload' => '{}',
            'status' => EventCandidate::STATUS_APPROVED,
            'translation_status' => EventCandidate::TRANSLATION_DONE,
            'published_event_id' => $event->id,
        ]);

        $this->artisan('events:translation-quality-report --sample=5')
            ->expectsOutputToContain('suspicious approved candidates found: 1')
            ->expectsOutputToContain((string) $candidate->id)
            ->assertExitCode(0);
    }
}
