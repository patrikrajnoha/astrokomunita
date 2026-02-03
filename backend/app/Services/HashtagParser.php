<?php

namespace App\Services;

use App\Models\Hashtag;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class HashtagParser
{
    /**
     * Extract hashtags from text content.
     * Supports [a-zA-Z0-9_], strips trailing punctuation, stores lowercase.
     * Max length 32, deduped.
     */
    public static function extract(string $content): Collection
    {
        // Regex: # followed by alphanumeric/underscore, max 32 chars
        // Excludes trailing punctuation like .,!?:;
        preg_match_all('/#([a-zA-Z0-9_]{1,32})[^\w\s]?/', $content, $matches);

        $hashtags = collect($matches[1] ?? [])
            ->map(fn ($tag) => strtolower($tag))
            ->unique()
            ->filter(fn ($tag) => strlen($tag) >= 1 && strlen($tag) <= 32);

        return $hashtags;
    }

    /**
     * Sync hashtags for a post: create missing hashtags and attach them.
     * Returns the collection of attached hashtags.
     */
    public static function syncHashtags(object $post, string $content): Collection
    {
        $hashtags = self::extract($content);

        if ($hashtags->isEmpty()) {
            $post->hashtags()->detach();
            return collect();
        }

        // Debug: Log extracted hashtags
        Log::info('HashtagParser: Extracted hashtags', [
            'content' => $content,
            'hashtags' => $hashtags->toArray(),
            'post_id' => $post->id ?? 'new'
        ]);

        // Get or create hashtags safely using firstOrCreate
        $hashtagModels = collect();
        foreach ($hashtags as $hashtag) {
            // Cast to string and normalize the hashtag name
            $hashtagName = self::normalizeHashtagName($hashtag);
            
            $hashtagModel = Hashtag::firstOrCreate(
                ['name' => $hashtagName]
            );
            
            $hashtagModels->put($hashtagName, $hashtagModel);
            
            // Debug: Log hashtag creation/retrieval
            Log::info('HashtagParser: Hashtag processed', [
                'hashtag_name' => $hashtagName,
                'hashtag_id' => $hashtagModel->id,
                'was_recently_created' => $hashtagModel->wasRecentlyCreated
            ]);
        }

        // Sync post with hashtags (exact match, no extra hashtags)
        $hashtagIds = $hashtagModels->pluck('id');
        $post->hashtags()->sync($hashtagIds);

        // Debug: Log sync result
        Log::info('HashtagParser: Hashtags synced to post', [
            'post_id' => $post->id ?? 'new',
            'hashtag_ids' => $hashtagIds->toArray(),
            'final_hashtags' => $hashtagModels->toArray()
        ]);

        return $hashtagModels;
    }

    /**
     * Normalize hashtag name to ensure it's a valid string.
     */
    private static function normalizeHashtagName($name): string
    {
        // Cast to string and trim whitespace
        $normalized = trim((string) $name);
        
        // Validate hashtag name is not empty
        if (empty($normalized)) {
            throw new \InvalidArgumentException('Hashtag name cannot be empty');
        }
        
        // Ensure it's not too long (database constraint)
        if (strlen($normalized) > 255) {
            throw new \InvalidArgumentException('Hashtag name cannot exceed 255 characters');
        }
        
        return $normalized;
    }
}
