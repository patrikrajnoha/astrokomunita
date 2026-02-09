<?php

namespace App\Services;

use App\Models\Post;
use App\Models\RssItem;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AstroBotPublisher
{
    private ?User $astroBot = null;

    /**
     * Publish an RSS item immediately as a Post.
     *
     * @param RssItem $item
     * @param array{content?:string, summary?:string} $overrides
     * @return Post
     * @throws \Throwable
     */
    public function publish(RssItem $item, array $overrides = []): Post
    {
        if (!$item->canPublish()) {
            throw new \InvalidArgumentException('Item cannot be published (status: ' . $item->status . ')');
        }

        return DB::transaction(function () use ($item, $overrides) {
            $content = $overrides['content'] ?? $this->buildContent($item);
            $summary = $overrides['summary'] ?? $item->summary;

            $ttlHours = (int) config('astrobot.post_ttl_hours', 24);
            $astroBot = $this->getAstroBotUser();
            $post = Post::create([
                'user_id' => $astroBot->id,
                'content' => $content,
                'source_name' => 'astrobot',
                'source_url' => $item->url,
                'source_uid' => $item->dedupe_hash,
                'source_published_at' => $item->published_at,
                'expires_at' => now()->addHours($ttlHours), // AstroBot posts expire after TTL hours
            ]);

            $item->update([
                'status' => RssItem::STATUS_PUBLISHED,
                'post_id' => $post->id,
                'last_error' => null,
            ]);

            return $post;
        });
    }

    /**
     * Schedule an RSS item for future publishing.
     *
     * @param RssItem $item
     * @param Carbon $when
     * @return void
     */
    public function schedule(RssItem $item, Carbon $when): void
    {
        if (!$item->canPublish()) {
            throw new \InvalidArgumentException('Item cannot be scheduled (status: ' . $item->status . ')');
        }

        $item->update([
            'status' => RssItem::STATUS_SCHEDULED,
            'scheduled_for' => $when,
            'last_error' => null,
        ]);
    }

    /**
     * Discard an RSS item with optional reason.
     *
     * @param RssItem $item
     * @param string|null $reason
     * @return void
     */
    public function discard(RssItem $item, ?string $reason = null): void
    {
        $item->update([
            'status' => RssItem::STATUS_DISCARDED,
            'scheduled_for' => null,
            'last_error' => $reason,
        ]);
    }

    /**
     * Publish all scheduled items whose time has come.
     *
     * @return int number of items published
     */
    public function publishScheduled(): int
    {
        $items = RssItem::scheduledReady()->get();
        $published = 0;

        foreach ($items as $item) {
            try {
                $this->publish($item);
                $published++;
            } catch (\Throwable $e) {
                $item->update([
                    'status' => RssItem::STATUS_ERROR,
                    'last_error' => $e->getMessage(),
                ]);
            }
        }

        return $published;
    }

    // ------------------------------------------------------------------
    // Private helpers
    // ------------------------------------------------------------------

    private function ensureAstroBotUser(): User
    {
        return User::query()->firstOrCreate(
            ['email' => 'astrobot@astrokomunita.local'],
            [
                'name' => 'AstroBot',
                'bio' => 'Automated space news from NASA RSS',
                'password' => \Illuminate\Support\Str::random(40),
                'is_bot' => true, // Mark as bot user for broadcast-only behavior
            ]
        );
    }

    private function getAstroBotUser(): User
    {
        if ($this->astroBot === null) {
            $this->astroBot = $this->ensureAstroBotUser();
        }

        return $this->astroBot;
    }

    private function buildContent(RssItem $item): string
    {
        $lines = [
            sprintf('🚀 NASA: %s', $item->title),
        ];

        if ($item->summary) {
            $lines[] = '';
            $lines[] = $item->summary;
        }

        $lines[] = '';
        $lines[] = $item->url;

        return implode("\n", $lines);
    }
}
