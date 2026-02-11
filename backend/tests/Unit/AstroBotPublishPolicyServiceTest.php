<?php

namespace Tests\Unit;

use App\Models\RssItem;
use App\Services\AstroBotPublishPolicyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AstroBotPublishPolicyServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_risk_keyword_returns_needs_review(): void
    {
        config()->set('astrobot.risk_keywords', ['crypto']);
        config()->set('astrobot.domain_whitelist', []);

        $item = RssItem::create([
            'source' => 'nasa_news',
            'guid' => 'risk-1',
            'url' => 'https://nasa.gov/risk-1',
            'dedupe_hash' => sha1('risk-1'),
            'stable_key' => sha1('risk-1'),
            'title' => 'Crypto launch update',
            'summary' => 'text',
            'status' => RssItem::STATUS_DRAFT,
            'fetched_at' => now(),
            'published_at' => now(),
        ]);

        $decision = app(AstroBotPublishPolicyService::class)->evaluate($item);
        $this->assertSame(AstroBotPublishPolicyService::DECISION_NEEDS_REVIEW, $decision);
    }

    public function test_domain_whitelist_fail_returns_needs_review(): void
    {
        config()->set('astrobot.risk_keywords', []);
        config()->set('astrobot.domain_whitelist', ['nasa.gov']);

        $item = RssItem::create([
            'source' => 'nasa_news',
            'guid' => 'domain-1',
            'url' => 'https://example.com/domain-1',
            'dedupe_hash' => sha1('domain-1'),
            'stable_key' => sha1('domain-1'),
            'title' => 'Regular title',
            'summary' => 'text',
            'status' => RssItem::STATUS_DRAFT,
            'fetched_at' => now(),
            'published_at' => now(),
        ]);

        $decision = app(AstroBotPublishPolicyService::class)->evaluate($item);
        $this->assertSame(AstroBotPublishPolicyService::DECISION_NEEDS_REVIEW, $decision);
    }

    public function test_safe_item_returns_publish(): void
    {
        config()->set('astrobot.risk_keywords', ['crypto', 'free']);
        config()->set('astrobot.domain_whitelist', ['nasa.gov']);

        $item = RssItem::create([
            'source' => 'nasa_news',
            'guid' => 'safe-1',
            'url' => 'https://nasa.gov/safe-1',
            'dedupe_hash' => sha1('safe-1'),
            'stable_key' => sha1('safe-1'),
            'title' => 'Mars mission status',
            'summary' => 'Normal content',
            'status' => RssItem::STATUS_DRAFT,
            'fetched_at' => now(),
            'published_at' => now(),
        ]);

        $decision = app(AstroBotPublishPolicyService::class)->evaluate($item);
        $this->assertSame(AstroBotPublishPolicyService::DECISION_PUBLISH, $decision);
    }
}

