<?php

namespace App\Console\Commands;

use App\Models\Post;
use App\Services\Moderation\ModerationService;
use Illuminate\Console\Command;

class RunPostModerationCommand extends Command
{
    protected $signature = 'moderation:run {post_id : ID postu na moderovanie}';

    protected $description = 'Run moderation immediately for a single post (local debug helper).';

    public function __construct(
        private readonly ModerationService $moderationService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if (!app()->environment('local')) {
            $this->error('This command is available only in local environment.');
            return self::FAILURE;
        }

        if (!(bool) config('moderation.enabled', true)) {
            $this->warn('Moderation is disabled (MODERATION_ENABLED=false).');
            return self::FAILURE;
        }

        $postId = (int) $this->argument('post_id');
        $post = Post::query()->find($postId);

        if (!$post) {
            $this->error("Post #{$postId} was not found.");
            return self::FAILURE;
        }

        $this->info("Running moderation for post #{$post->id}...");

        try {
            $this->moderationService->moderatePost($post);
        } catch (\Throwable $exception) {
            $this->error('Moderation failed: ' . $exception->getMessage());
            return self::FAILURE;
        }

        $post->refresh();

        $this->info('Done.');
        $this->line('moderation_status: ' . (string) $post->moderation_status);
        $this->line('attachment_moderation_status: ' . (string) ($post->attachment_moderation_status ?? 'n/a'));
        $this->line('is_hidden: ' . ($post->is_hidden ? 'true' : 'false'));

        return self::SUCCESS;
    }
}
