<?php

namespace Tests\Unit;

use App\Enums\BotPublishStatus;
use App\Enums\BotSourceType;
use App\Enums\BotTranslationStatus;
use App\Models\BotItem;
use App\Models\BotSource;
use App\Services\Bots\BotItemDedupeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BotItemDedupeServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_upsert_by_stable_key_does_not_create_duplicates(): void
    {
        $source = BotSource::query()->create([
            'key' => 'test.nasa.rss',
            'bot_identity' => 'stela',
            'source_type' => BotSourceType::RSS->value,
            'url' => 'https://example.test/feed.xml',
            'is_enabled' => true,
            'schedule' => ['cron' => '*/30 * * * *'],
        ]);

        $service = app(BotItemDedupeService::class);

        $first = $service->upsertByStableKey($source, 'same-key', [
            'title' => 'First title',
            'summary' => 'First summary',
            'translation_status' => BotTranslationStatus::PENDING->value,
            'publish_status' => BotPublishStatus::PENDING->value,
        ]);

        $second = $service->upsertByStableKey($source, 'same-key', [
            'title' => 'Updated title',
            'summary' => 'Updated summary',
            'translation_status' => BotTranslationStatus::DONE->value,
            'publish_status' => BotPublishStatus::SKIPPED->value,
        ]);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, BotItem::query()->count());
        $this->assertDatabaseHas('bot_items', [
            'id' => $first->id,
            'source_id' => $source->id,
            'stable_key' => 'same-key',
            'title' => 'Updated title',
            'translation_status' => BotTranslationStatus::DONE->value,
            'publish_status' => BotPublishStatus::SKIPPED->value,
        ]);
    }
}

