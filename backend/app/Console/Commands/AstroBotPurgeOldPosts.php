<?php

namespace App\Console\Commands;

use App\Models\Post;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AstroBotPurgeOldPosts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'astrobot:purge-old-posts 
                            {--dry-run : Show what would be deleted without actually deleting}
                            {--hours= : Number of hours after which posts should be deleted (overrides config)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Purge AstroBot posts older than specified hours (default: 24h) with robust fallback for posts without expires_at';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $isDryRun = $this->option('dry-run');
        $hoursOption = $this->option('hours');
        $hours = $hoursOption !== null
            ? (int) $hoursOption
            : (int) config('astrobot.post_ttl_hours', 24);
        $maxPerRun = (int) config('astrobot.max_posts_per_cleanup', 100);
        
        $this->info("Starting AstroBot posts purge (older than {$hours} hours)...");
        
        // Find AstroBot user
        $astroBot = User::where('is_bot', true)->first();
        
        if (!$astroBot) {
            $this->error('AstroBot user not found. Make sure AstroBotSeeder has been run.');
            return 1;
        }
        
        $this->info("Found AstroBot user: {$astroBot->name} (ID: {$astroBot->id})");
        
        $cutoffTime = now()->subHours($hours);
        $this->info("Cutoff time: {$cutoffTime->format('Y-m-d H:i:s')} (UTC)");
        
        // Use created_at cutoff (authoritative 24h rule)
        $oldPostsQuery = Post::where('user_id', $astroBot->id)
            ->where('created_at', '<=', $cutoffTime)
            ->where('is_hidden', false)
            ->orderBy('id')
            ->limit($maxPerRun);
        
        $oldPosts = $oldPostsQuery->get();
        
        if ($oldPosts->isEmpty()) {
            $this->info('No old AstroBot posts found to purge.');
            return 0;
        }
        
        $this->info("Found {$oldPosts->count()} AstroBot posts to purge:");
        
        // Show details for each post
        $deletedCount = 0;
        foreach ($oldPosts as $post) {
            $createdAt = $post->created_at->format('Y-m-d H:i:s');
            $expiresAt = $post->expires_at ? $post->expires_at->format('Y-m-d H:i:s') : 'NULL';
            $likesCount = $post->likes()->count();
            $repliesCount = $post->replies()->count();
            $tagsCount = $post->tags()->count();
            $hashtagsCount = $post->hashtags()->count();
            
            $this->line("- Post #{$post->id} (created: {$createdAt}, expires: {$expiresAt})");
            $this->line("  Likes: {$likesCount}, Replies: {$repliesCount}, Tags: {$tagsCount}, Hashtags: {$hashtagsCount}");
            
            if (!$isDryRun) {
                try {
                    // Use transaction to ensure data integrity
                    DB::transaction(function () use ($post) {
                        // Delete the post - cascade will handle related records
                        $post->delete();
                    });
                    
                    $this->line("  ✓ Permanently deleted");
                    $deletedCount++;
                    
                    // Log the deletion
                    Log::info('AstroBot post purged', [
                        'post_id' => $post->id,
                        'created_at' => $post->created_at,
                        'expires_at' => $post->expires_at,
                        'purged_at' => now(),
                    ]);
                    
                } catch (\Exception $e) {
                    $this->error("  ✗ Failed to delete: {$e->getMessage()}");
                    Log::error('Failed to purge AstroBot post', [
                        'post_id' => $post->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            } else {
                $this->line("  [DRY RUN] Would permanently delete");
            }
        }
        
        if ($isDryRun) {
            $this->info("Dry run completed. Use without --dry-run to actually delete posts.");
        } else {
            $this->info("Successfully purged {$deletedCount} old AstroBot posts and all related data.");
        }
        
        return 0;
    }
}
