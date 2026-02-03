<?php

namespace App\Console\Commands;

use App\Models\Post;
use App\Models\User;
use Illuminate\Console\Command;

class CleanupExpiredAstroBotPosts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'astrobot:cleanup-expired {--dry-run : Show what would be deleted without actually deleting}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cleanup expired AstroBot posts (older than 24 hours) by marking them as hidden';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $isDryRun = $this->option('dry-run');
        
        $this->info('Starting AstroBot post cleanup...');
        
        // Find expired AstroBot posts
        $expiredPosts = Post::query()
            ->whereHas('user', function ($query) {
                $query->where('is_bot', true);
            })
            ->expired()
            ->where('is_hidden', false) // Only process visible posts
            ->get();

        if ($expiredPosts->isEmpty()) {
            $this->info('No expired AstroBot posts found.');
            return 0;
        }

        $this->info("Found {$expiredPosts->count()} expired AstroBot posts:");

        foreach ($expiredPosts as $post) {
            $expiredAt = $post->expires_at->format('Y-m-d H:i:s');
            $createdAt = $post->created_at->format('Y-m-d H:i:s');
            
            $this->line("- Post #{$post->id} (expired: {$expiredAt}, created: {$createdAt})");
            
            if (!$isDryRun) {
                // Mark as hidden instead of deleting to preserve data
                $post->update([
                    'is_hidden' => true,
                    'hidden_reason' => 'expired_astrobot_post'
                ]);
                
                $this->line("  ✓ Marked as hidden");
            } else {
                $this->line("  [DRY RUN] Would mark as hidden");
            }
        }

        if ($isDryRun) {
            $this->info('Dry run completed. Use --no-dry-run to actually hide posts.');
        } else {
            $this->info("Successfully marked {$expiredPosts->count()} expired AstroBot posts as hidden.");
        }

        return 0;
    }
}
