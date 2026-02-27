<?php

namespace Tests\Unit;

use App\Enums\BotSourceType;
use App\Models\BotSource;
use App\Services\Bots\BotRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class BotRunnerLockTest extends TestCase
{
    use RefreshDatabase;

    public function test_runner_skips_when_lock_is_already_held(): void
    {
        config()->set('moderation.enabled', false);

        $source = BotSource::query()->create([
            'key' => 'nasa_rss_breaking',
            'bot_identity' => 'kozmo',
            'source_type' => BotSourceType::RSS->value,
            'url' => 'https://example.test/rss.xml',
            'is_enabled' => true,
            'schedule' => null,
        ]);

        $lock = Cache::lock('bots:run:' . $source->key, 600);
        $this->assertTrue($lock->get());

        try {
            $run = app(BotRunner::class)->run($source, 'scheduled');

            $this->assertSame('skipped', (string) ($run->status?->value ?? $run->status));
            $this->assertSame(1, (int) ($run->stats['run_locked'] ?? 0));
            $this->assertSame('bots:run:' . $source->key, (string) ($run->stats['lock_key'] ?? ''));
            $this->assertDatabaseCount('posts', 0);
            $this->assertDatabaseCount('bot_items', 0);
        } finally {
            $lock->release();
        }
    }

    public function test_runner_releases_locks_when_run_throws_exception(): void
    {
        config()->set('moderation.enabled', false);

        $source = BotSource::query()->create([
            'key' => 'unsupported_api_source_for_lock_test',
            'bot_identity' => 'kozmo',
            'source_type' => BotSourceType::API->value,
            'url' => 'https://example.test/api',
            'is_enabled' => true,
            'schedule' => null,
        ]);

        $run = app(BotRunner::class)->run($source, 'scheduled');
        $this->assertSame('failed', (string) ($run->status?->value ?? $run->status));

        $contextLock = Cache::lock('bots:run:scheduled:' . $source->key, 600);
        $globalLock = Cache::lock('bots:run:' . $source->key, 600);

        try {
            $this->assertTrue($contextLock->get());
            $this->assertTrue($globalLock->get());
        } finally {
            $contextLock->release();
            $globalLock->release();
        }
    }
}
