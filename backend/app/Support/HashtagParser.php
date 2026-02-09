<?php

namespace App\Support;

use App\Models\Hashtag;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class HashtagParser
{
    /**
     * @return Collection<int, string>
     */
    public static function extract(string $content): Collection
    {
        preg_match_all('/#([a-zA-Z0-9_]{1,32})[^\w\s]?/', $content, $matches);

        return collect($matches[1] ?? [])
            ->map(fn ($tag) => strtolower($tag))
            ->unique()
            ->filter(fn ($tag) => strlen($tag) >= 1 && strlen($tag) <= 32)
            ->values();
    }

    public static function syncHashtags(object $post, string $content): Collection
    {
        $hashtags = self::extract($content);

        if ($hashtags->isEmpty()) {
            $post->hashtags()->detach();
            return collect();
        }

        Log::info('HashtagParser: Extracted hashtags', [
            'content' => $content,
            'hashtags' => $hashtags->toArray(),
            'post_id' => $post->id ?? 'new',
        ]);

        $hashtagModels = collect();
        foreach ($hashtags as $hashtag) {
            $hashtagName = self::normalizeHashtagName($hashtag);

            $hashtagModel = Hashtag::firstOrCreate([
                'name' => $hashtagName,
            ]);

            $hashtagModels->put($hashtagName, $hashtagModel);

            Log::info('HashtagParser: Hashtag processed', [
                'hashtag_name' => $hashtagName,
                'hashtag_id' => $hashtagModel->id,
                'was_recently_created' => $hashtagModel->wasRecentlyCreated,
            ]);
        }

        $hashtagIds = $hashtagModels->pluck('id');
        $post->hashtags()->sync($hashtagIds);

        Log::info('HashtagParser: Hashtags synced to post', [
            'post_id' => $post->id ?? 'new',
            'hashtag_ids' => $hashtagIds->toArray(),
            'final_hashtags' => $hashtagModels->toArray(),
        ]);

        return $hashtagModels;
    }

    public static function syncTags(object $post, string $content): Collection
    {
        return self::syncHashtags($post, $content);
    }

    private static function normalizeHashtagName(mixed $name): string
    {
        $normalized = trim((string) $name);

        if ($normalized === '') {
            throw new \InvalidArgumentException('Hashtag name cannot be empty');
        }

        if (strlen($normalized) > 255) {
            throw new \InvalidArgumentException('Hashtag name cannot exceed 255 characters');
        }

        return $normalized;
    }
}
