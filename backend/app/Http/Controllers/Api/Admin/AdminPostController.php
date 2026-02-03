<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminPostController extends Controller
{
    /**
     * PATCH /api/admin/posts/{id}/pin
     * 
     * Pin a post (unpins all others first)
     */
    public function pin(Request $request, Post $post)
    {
        DB::transaction(function () use ($post) {
            // Unpin all posts first
            Post::whereNotNull('pinned_at')->update(['pinned_at' => null]);
            
            // Pin the selected post
            $post->update(['pinned_at' => now()]);
        });

        return response()->json([
            'message' => 'Post pinned successfully',
            'post' => $post->fresh(['user:id,name,username'])
        ]);
    }

    /**
     * PATCH /api/admin/posts/{id}/unpin
     * 
     * Unpin a post
     */
    public function unpin(Request $request, Post $post)
    {
        $post->update(['pinned_at' => null]);

        return response()->json([
            'message' => 'Post unpinned successfully',
            'post' => $post->fresh(['user:id,name,username'])
        ]);
    }
}
