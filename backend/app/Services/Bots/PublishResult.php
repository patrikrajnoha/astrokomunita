<?php

namespace App\Services\Bots;

use App\Models\Post;

class PublishResult
{
    private function __construct(
        public readonly string $status,
        public readonly ?Post $post = null,
        public readonly ?string $reason = null,
    ) {
    }

    public static function published(Post $post): self
    {
        return new self('published', $post, null);
    }

    public static function skipped(string $reason): self
    {
        return new self('skipped', null, $reason);
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    public function isSkipped(): bool
    {
        return $this->status === 'skipped';
    }
}
