<?php

namespace App\Console\Commands;

use App\Models\Post;
use App\Services\PostService;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;
use Throwable;

class RepairPostImageVariantsCommand extends Command
{
    protected $signature = 'posts:repair-image-variants
        {post_ids* : IDs postov, pre ktore sa ma znovu vygenerovat obrazkovy web variant}';

    protected $description = 'Rebuild public image variants for posts from their stored original image files.';

    public function __construct(
        private readonly PostService $posts,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $postIds = collect((array) $this->argument('post_ids'))
            ->map(fn (mixed $value): int => (int) $value)
            ->filter(fn (int $value): bool => $value > 0)
            ->unique()
            ->values();

        if ($postIds->isEmpty()) {
            $this->error('Provide at least one valid post ID.');
            return self::FAILURE;
        }

        $posts = Post::query()
            ->whereIn('id', $postIds->all())
            ->get()
            ->keyBy('id');

        $repaired = 0;
        $failed = 0;

        foreach ($postIds as $postId) {
            $post = $posts->get($postId);
            if (!$post) {
                $this->error("Post #{$postId} was not found.");
                $failed++;
                continue;
            }

            try {
                $repairedPost = $this->posts->repairImageAttachmentVariants($post);
                $this->line(sprintf(
                    'post_id=%d status=repaired web_path=%s web_mime=%s web_size=%d',
                    (int) $repairedPost->id,
                    (string) ($repairedPost->attachment_web_path ?? ''),
                    (string) ($repairedPost->attachment_web_mime ?? ''),
                    (int) ($repairedPost->attachment_web_size ?? 0),
                ));
                $repaired++;
            } catch (ValidationException $exception) {
                $message = collect($exception->errors())->flatten()->first() ?: $exception->getMessage();
                $this->error("Post #{$postId} could not be repaired: {$message}");
                $failed++;
            } catch (Throwable $exception) {
                $this->error("Post #{$postId} repair failed: {$exception->getMessage()}");
                $failed++;
            }
        }

        $this->line(sprintf('repaired=%d failed=%d', $repaired, $failed));

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
