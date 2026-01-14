<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Post;
use Illuminate\Http\Request;

class PostController extends Controller
{
    // GET /api/posts (globálny feed)
    // GET /api/posts?scope=me (moje príspevky - vyžaduje auth)
    public function index(Request $request)
    {
        $query = Post::query()
            ->with(['user:id,name,email,location,bio,is_admin'])
            ->latest();

        if ($request->query('scope') === 'me') {
            $user = $request->user();
            if (!$user) {
                return response()->json(['message' => 'Neprihlásený.'], 401);
            }
            $query->where('user_id', $user->id);
        }

        return response()->json($query->paginate(20));
    }

    // POST /api/posts (auth)
    public function store(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'content' => ['required', 'string', 'min:1', 'max:280'],
        ]);

        $post = Post::create([
            'user_id' => $user->id,
            'content' => $data['content'],
        ])->load('user:id,name,email,location,bio,is_admin');

        return response()->json($post, 201);
    }
}
