<?php

namespace Tests\Feature;

use App\Jobs\TranslateRssItemJob;
use App\Models\RssItem;
use App\Services\AstroBotNasaService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class TranslateRssItemJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_upsert_dispatches_translation_job_after_commit(): void
    {
        Queue::fake();

        DB::transaction(function (): void {
            app(AstroBotNasaService::class)->upsertItems([
                [
                    'guid' => 'translate-guid-1',
                    'link' => 'https://www.nasa.gov/news/translate-guid-1',
                    'title' => 'Title',
                    'summary' => 'Summary',
                    'published_at' => Carbon::parse('2026-02-12 10:00:00'),
                    'fingerprint' => sha1('translate-guid-1'),
                ],
            ]);
        });

        Queue::assertPushed(TranslateRssItemJob::class, 1);
    }

    public function test_job_marks_item_done_and_saves_translations(): void
    {
        config()->set('services.translation.base_url', 'http://translation.test');
        config()->set('services.translation.internal_token', 'token');

        Http::fake([
            'http://translation.test/*' => Http::response([
                'translated' => 'Prelozene',
                'meta' => [
                    'engine' => 'argos',
                    'from' => 'en',
                    'to' => 'sk',
                    'took_ms' => 10,
                ],
            ], 200),
        ]);

        $item = RssItem::query()->create([
            'source' => AstroBotNasaService::SOURCE,
            'guid' => 'job-ok',
            'url' => 'https://www.nasa.gov/news/job-ok',
            'dedupe_hash' => sha1('job-ok'),
            'stable_key' => sha1('job-ok'),
            'title' => 'Original title',
            'summary' => 'Original summary',
            'status' => RssItem::STATUS_DRAFT,
            'translation_status' => RssItem::TRANSLATION_PENDING,
            'fetched_at' => now(),
            'published_at' => now(),
        ]);

        (new TranslateRssItemJob($item->id))->handle(app(\App\Services\TranslationService::class));

        $item->refresh();
        $this->assertSame(RssItem::TRANSLATION_DONE, $item->translation_status);
        $this->assertSame('Prelozene', $item->translated_title);
        $this->assertSame('Prelozene', $item->translated_summary);
        $this->assertNotNull($item->translated_at);
    }

    public function test_job_marks_item_failed_when_translation_service_errors(): void
    {
        config()->set('services.translation.base_url', 'http://translation.test');
        config()->set('services.translation.internal_token', 'token');

        Http::fake([
            'http://translation.test/*' => Http::response(['error' => 'boom'], 500),
        ]);

        $item = RssItem::query()->create([
            'source' => AstroBotNasaService::SOURCE,
            'guid' => 'job-fail',
            'url' => 'https://www.nasa.gov/news/job-fail',
            'dedupe_hash' => sha1('job-fail'),
            'stable_key' => sha1('job-fail'),
            'title' => 'Original title',
            'summary' => 'Original summary',
            'status' => RssItem::STATUS_DRAFT,
            'translation_status' => RssItem::TRANSLATION_PENDING,
            'fetched_at' => now(),
            'published_at' => now(),
        ]);

        try {
            (new TranslateRssItemJob($item->id))->handle(app(\App\Services\TranslationService::class));
            $this->fail('Expected translation job to throw on HTTP 500.');
        } catch (\Throwable) {
            $item->refresh();
            $this->assertSame(RssItem::TRANSLATION_FAILED, $item->translation_status);
            $this->assertSame('http_500', $item->translation_error);
        }
    }

    public function test_job_is_idempotent_when_translation_is_done(): void
    {
        Http::fake();

        $item = RssItem::query()->create([
            'source' => AstroBotNasaService::SOURCE,
            'guid' => 'job-idempotent',
            'url' => 'https://www.nasa.gov/news/job-idempotent',
            'dedupe_hash' => sha1('job-idempotent'),
            'stable_key' => sha1('job-idempotent'),
            'title' => 'Original title',
            'summary' => 'Original summary',
            'original_title' => 'Original title',
            'original_summary' => 'Original summary',
            'translated_title' => 'Uz prelozene',
            'translated_summary' => 'Uz prelozene summary',
            'translation_status' => RssItem::TRANSLATION_DONE,
            'translated_at' => now(),
            'status' => RssItem::STATUS_DRAFT,
            'fetched_at' => now(),
            'published_at' => now(),
        ]);

        (new TranslateRssItemJob($item->id))->handle(app(\App\Services\TranslationService::class));

        Http::assertNothingSent();
        $item->refresh();
        $this->assertSame('Uz prelozene', $item->translated_title);
        $this->assertSame(RssItem::TRANSLATION_DONE, $item->translation_status);
    }
}
