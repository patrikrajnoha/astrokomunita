<?php

namespace App\Console\Commands;

use App\Models\Post;
use App\Support\HashtagParser;
use Illuminate\Console\Command;

class FixHashtags extends Command
{
    protected $signature = 'hashtags:fix {tag? : Specific tag to fix}';
    protected $description = 'Fix missing hashtag relationships for existing posts';

    public function handle()
    {
        $tagFilter = $this->argument('tag');
        
        if ($tagFilter) {
            $posts = Post::where('content', 'like', "%#{$tagFilter}%")->get();
            $this->info("Processing posts with hashtag #{$tagFilter}...");
        } else {
            $posts = Post::where('content', 'like', '%#%')->get();
            $this->info('Processing all posts with hashtags...');
        }

        $this->info("Found {$posts->count()} posts to process");

        foreach ($posts as $post) {
            $this->line("Processing post ID: {$post->id}");
            try {
                HashtagParser::syncHashtags($post, $post->content);
                $this->info("✓ Fixed post {$post->id}");
            } catch (\Exception $e) {
                $this->error("✗ Error fixing post {$post->id}: " . $e->getMessage());
            }
        }

        $this->info('Done!');
        return 0;
    }
}
