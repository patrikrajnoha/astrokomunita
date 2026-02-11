<?php

namespace App\Services;

use App\Models\Post;
use App\Models\RssItem;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AstroBotPublisher
{
    private ?User $astroBot = null;

    /**
     * @param array{content?:string, summary?:string} $overrides
     */
    public function publish(RssItem $item, array $overrides = []): Post
    {
        if (! $item->canPublish()) {
            throw new \InvalidArgumentException('Item cannot be published (status: ' . $item->status . ')');
        }

        return DB::transaction(function () use ($item, $overrides) {
            $content = (string) ($overrides['content'] ?? $this->buildContent($item));
            $sourceUid = (string) ($item->stable_key ?: $item->dedupe_hash);
            $ttlHours = (int) config('astrobot.post_ttl_hours', 24);

            $post = Post::query()->firstOrNew(['source_uid' => $sourceUid]);
            $post->fill([
                'user_id' => $this->getAstroBotUser()->id,
                'content' => $content,
                'source_name' => 'astrobot',
                'source_url' => $item->url,
                'source_uid' => $sourceUid,
                'source_published_at' => $item->published_at,
                'expires_at' => now()->addHours($ttlHours),
            ]);
            $post->save();

            $item->update([
                'status' => RssItem::STATUS_PUBLISHED,
                'post_id' => $post->id,
                'published_to_posts_at' => now(),
                'reviewed_at' => $item->reviewed_at ?? now(),
                'last_error' => null,
            ]);

            return $post;
        });
    }

    public function schedule(RssItem $item, Carbon $when): void
    {
        if (! $item->canPublish()) {
            throw new \InvalidArgumentException('Item cannot be scheduled (status: ' . $item->status . ')');
        }

        $item->update([
            'status' => RssItem::STATUS_SCHEDULED,
            'scheduled_for' => $when,
            'last_error' => null,
        ]);
    }

    public function discard(RssItem $item, ?string $reason = null): void
    {
        $this->reject($item, $reason);
    }

    public function reject(RssItem $item, ?string $reason = null, ?int $reviewedBy = null): void
    {
        $item->update([
            'status' => RssItem::STATUS_REJECTED,
            'scheduled_for' => null,
            'review_note' => $reason,
            'reviewed_by' => $reviewedBy,
            'reviewed_at' => now(),
            'last_error' => $reason,
        ]);
    }

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
                    'status' => RssItem::STATUS_NEEDS_REVIEW,
                    'last_error' => $e->getMessage(),
                ]);
            }
        }

        return $published;
    }

    private function ensureAstroBotUser(): User
    {
        return User::query()->firstOrCreate(
            ['email' => 'astrobot@astrokomunita.local'],
            [
                'name' => 'AstroBot',
                'username' => 'astrobot',
                'bio' => 'Automated space news from NASA RSS',
                'password' => Str::random(40),
                'is_bot' => true,
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
            sprintf('NASA: %s', $item->title),
        ];

        if ($item->summary) {
            $lines[] = '';
            $lines[] = (string) $item->summary;
        }

        $lines[] = '';
        $lines[] = (string) $item->url;

        return implode("\n", $lines);
    }
}

