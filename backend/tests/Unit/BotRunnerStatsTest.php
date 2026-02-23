<?php

namespace Tests\Unit;

use App\Enums\BotSourceType;
use App\Models\BotItem;
use App\Models\BotRun;
use App\Models\BotSource;
use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BotRunnerStatsTest extends TestCase
{
    use RefreshDatabase;

    public function test_runner_stats_include_observability_fields_and_run_context_meta(): void
    {
        config()->set('moderation.enabled', false);

        $source = BotSource::query()->create([
            'key' => 'nasa_rss_breaking',
            'bot_identity' => 'kozmo',
            'source_type' => BotSourceType::RSS->value,
            'url' => 'https://example.test/nasa.xml',
            'is_enabled' => true,
            'schedule' => null,
        ]);

        Http::fake([
            $source->url => Http::response((string) file_get_contents(base_path('tests/Fixtures/nasa_rss.xml')), 200, [
                'Content-Type' => 'application/rss+xml',
            ]),
        ]);

        $exitCode = Artisan::call('bots:run', [
            'sourceKey' => $source->key,
            '--context' => 'cli',
        ]);

        $this->assertSame(0, $exitCode);

        $run = BotRun::query()->latest('id')->firstOrFail();
        $stats = is_array($run->stats) ? $run->stats : [];
        $this->assertArrayHasKey('translation_done_count', $stats);
        $this->assertArrayHasKey('translation_failed_count', $stats);
        $this->assertArrayHasKey('wikidata_checked_count', $stats);
        $this->assertArrayHasKey('wikidata_cached_hits', $stats);
        $this->assertArrayHasKey('image_skipped_policy_count', $stats);
        $this->assertArrayHasKey('error_fingerprints', $stats);
        $this->assertSame('cli', (string) ($stats['run_context'] ?? ''));

        $item = BotItem::query()->latest('id')->firstOrFail();
        $post = Post::query()->latest('id')->firstOrFail();
        $this->assertSame('cli', (string) data_get($item->meta, 'run_context'));
        $this->assertSame('cli', (string) data_get($post->meta, 'run_context'));
    }
}
