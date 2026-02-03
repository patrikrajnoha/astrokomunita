<?php

namespace App\Services;

use App\Models\Post;
use App\Models\User;
use App\Services\HashtagParser;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PostService
{
    /**
     * Create a new post
     */
    public function createPost(array $data, User $user): Post
    {
        return DB::transaction(function () use ($data, $user) {
            $post = new Post();
            $post->user_id = $user->id;
            $post->content = $data['content'];
            $post->parent_id = $data['parent_id'] ?? null;
            $post->is_hidden = false;
            
            // Handle attachment if present
            if (isset($data['attachment']) && $data['attachment'] instanceof UploadedFile) {
                $path = $data['attachment']->store('attachments', 'public');
                $post->attachment_path = $path;
            }
            
            $post->save();
            
            // Parse and attach hashtags
            HashtagParser::syncTags($post, $post->content);
            
            return $post->load(['user', 'tags']);
        });
    }

    /**
     * Update an existing post
     */
    public function updatePost(Post $post, array $data): Post
    {
        return DB::transaction(function () use ($post, $data) {
            if (isset($data['content'])) {
                $post->content = $data['content'];
                HashtagParser::syncTags($post, $post->content);
            }
            
            if (isset($data['is_hidden'])) {
                $post->is_hidden = $data['is_hidden'];
            }
            
            $post->save();
            
            return $post->load(['user', 'tags']);
        });
    }

    /**
     * Delete a post and its relationships
     */
    public function deletePost(Post $post): bool
    {
        return DB::transaction(function () use ($post) {
            // Delete attachment if exists
            if ($post->attachment_path) {
                Storage::disk('public')->delete($post->attachment_path);
            }
            
            // Delete relationships
            $post->tags()->detach();
            $post->likes()->delete();
            
            // Delete replies
            $post->replies()->delete();
            
            return $post->delete();
        });
    }

    /**
     * Build posts query with filters
     */
    public function buildPostsQuery(array $filters = [])
    {
        $query = Post::query()
            ->with([
                'user:id,name,username,email,location,bio,is_admin,avatar_path',
                'replies.user:id,name,username,email,location,bio,is_admin,avatar_path',
                'parent.user:id,name,username,email,location,bio,is_admin,avatar_path',
                'tags:id,name',
            ])
            ->latest();

        // Apply filters
        if (isset($filters['kind'])) {
            match ($filters['kind']) {
                'replies' => $query->whereNotNull('parent_id'),
                'media' => $query->whereNotNull('attachment_path'),
                default => $query->whereNull('parent_id')
            };
        }

        if (isset($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        if (isset($filters['source'])) {
            match ($filters['source']) {
                'astrobot' => $query->where('source_name', 'astrobot'),
                'users' => $query->whereNull('source_name'),
                default => $query->where('source_name', $filters['source'])
            };
        }

        if (!($filters['include_hidden'] ?? false)) {
            $query->where('is_hidden', false);
        }

        // Exclude expired AstroBot posts from public feed
        $query->notExpired();

        return $query;
    }

    /**
     * Get posts with pagination
     */
    public function getPostsPaginated(array $filters = [], int $perPage = 20)
    {
        $perPage = max(1, min($perPage, 50)); // Clamp between 1-50
        
        $query = $this->buildPostsQuery($filters);
        
        // Add counts if requested
        if ($filters['with_counts'] ?? false) {
            $query->withCount(['likes', 'replies']);
        } else {
            $query->withCount(['likes']);
        }
        
        // Add liked_by_me if user is authenticated
        if (isset($filters['user'])) {
            $query->withExists([
                'likes as liked_by_me' => fn ($q) => $q->where('user_id', $filters['user']->id),
            ]);
        }
        
        return $query->paginate($perPage);
    }
}
