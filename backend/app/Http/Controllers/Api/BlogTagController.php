<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tag;

class BlogTagController extends Controller
{
    public function index()
    {
        $tags = Tag::query()
            ->select(['id', 'name', 'slug'])
            ->whereHas('blogPosts', fn ($q) => $q->published())
            ->withCount([
                'blogPosts as published_posts_count' => fn ($q) => $q->published(),
            ])
            ->orderByDesc('published_posts_count')
            ->get();

        return response()->json($tags);
    }
}
