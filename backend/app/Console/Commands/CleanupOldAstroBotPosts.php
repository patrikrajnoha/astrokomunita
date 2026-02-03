<?php

namespace App\Console\Commands;

use App\Models\Post;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanupOldAstroBotPosts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'astrobot:cleanup-posts {--dry-run : Show what would be deleted without actually deleting}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Permanently delete AstroBot posts older than 24 hours with all related data';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $isDryRun = $this->option('dry-run');
        
        $this->info('Starting AstroBot posts cleanup (permanent deletion)...');
        
        // Find AstroBot user
        $astroBot = User::where('is_bot', true)->first();
        
        if (!$astroBot) {
            $this->error('AstroBot user not found. Make sure AstroBotSeeder has been run.');
            return 1;
        }
        
        $this->info("Found AstroBot user: {$astroBot->name} (ID: {$astroBot->id})");
        
        // Find posts older than 24 hours from AstroBot
        $cutoffTime = now()->subDay();
        $oldPostsQuery = Post::where('user_id', $astroBot->id)
                            ->where('created_at', '<', $cutoffTime);
        
        $oldPosts = $oldPostsQuery->get();
        
        if ($oldPosts->isEmpty()) {
            $this->info('No AstroBot posts older than 24 hours found.');
            return 0;
        }
        
        $this->info("Found {$oldPosts->count()} AstroBot posts older than 24 hours:");
        
        // Show details for each post
        foreach ($oldPosts as $post) {
            $createdAt = $post->created_at->format('Y-m-d H:i:s');
            $likesCount = $post->likes()->count();
            $repliesCount = $post->replies()->count();
            $tagsCount = $post->tags()->count();
            $hashtagsCount = $post->hashtags()->count();
            
            $this->line("- Post #{$post->id} (created: {$createdAt})");
            $this->line("  Likes: {$likesCount}, Replies: {$repliesCount}, Tags: {$tagsCount}, Hashtags: {$hashtagsCount}");
            
            if (!$isDryRun) {
                // Use transaction to ensure data integrity
                DB::transaction(function () use ($post) {
                    // Delete the post - cascade will handle related records
                    $post->delete();
                });
                
                $this->line("  ✓ Permanently deleted");
            } else {
                $this->line("  [DRY RUN] Would permanently delete");
            }
        }
        
        if ($isDryRun) {
            $this->info('Dry run completed. Use --no-dry-run to actually delete posts.');
        } else {
            $this->info("Successfully deleted {$oldPosts->count()} old AstroBot posts and all related data.");
        }
        
        return 0;
    }
}
