<?php

namespace App\Jobs;

use App\Models\Post;
use App\Services\Moderation\ModerationService;
use App\Services\Moderation\ModerationTemporaryException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ModeratePostJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 5;

    public function __construct(
        public readonly int $postId,
    ) {
    }

    public function backoff(): array
    {
        return [30, 120, 300, 900, 1800];
    }

    public function handle(ModerationService $moderationService): void
    {
        if (!config('moderation.enabled', true)) {
            return;
        }

        $post = Post::query()->find($this->postId);
        if (!$post) {
            return;
        }

        if ($post->moderation_status !== 'pending') {
            return;
        }

        try {
            $moderationService->moderatePost($post);
        } catch (ModerationTemporaryException $exception) {
            report($exception);

            if (config('queue.default') !== 'sync') {
                $this->release(60);
            }
        }
    }
}
