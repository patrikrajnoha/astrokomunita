<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PostController extends Controller
{
    /**
     * GET /api/posts
     * GET /api/posts?scope=me
     *
     * Verejný komunitný feed
     * + možnosť zobraziť len príspevky prihláseného používateľa
     *
     * MVP replies:
     * - feed vracia iba root posty (parent_id NULL)
     * - pridáva replies_count
     */
    public function index(Request $request)
    {
        $query = Post::query()
            ->whereNull('parent_id')
            ->with([
                'user:id,name,email,location,bio,is_admin',
            ])
            ->withCount('replies')
            ->latest();

        // filtrovanie len na moje posty
        if ($request->query('scope') === 'me') {
            $user = $request->user();

            if (!$user) {
                return response()->json([
                    'message' => 'Neprihlásený používateľ.',
                ], 401);
            }

            $query->where('user_id', $user->id);
        }

        return response()->json(
            $query->paginate(20)
        );
    }

    /**
     * GET /api/posts/{post}
     *
     * Detail root príspevku + jeho replies
     * Verejne dostupný
     */
    public function show(Post $post)
    {
        // Ak niekto otvorí reply URL priamo, vrátime detail jeho root postu + replies rootu
        $root = $post->parent_id ? $post->parent()->firstOrFail() : $post;

        $root->load([
            'user:id,name,email,location,bio,is_admin',
        ]);

        $replies = Post::query()
            ->where('parent_id', $root->id)
            ->with([
                'user:id,name,email,location,bio,is_admin',
            ])
            ->latest()
            ->get();

        return response()->json([
            'post' => $root,
            'replies' => $replies,
        ]);
    }

    /**
     * POST /api/posts
     *
     * Vytvorenie príspevku (vyžaduje auth)
     * Podporuje text + 1 prílohu
     * + voliteľne parent_id (reply na root)
     */
    public function store(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'content' => [
                'required',
                'string',
                'min:1',
                'max:280',
            ],

            // reply (voliteľné)
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('posts', 'id'),
            ],

            // 5 MB limit, bezpečné typy (MVP)
            'attachment' => [
                'nullable',
                'file',
                'max:5120',
                'mimes:jpg,jpeg,png,webp,gif,pdf,txt,doc,docx',
            ],
        ]);

        // Ak je parent_id, musí to byť ROOT post (zakáž reply na reply)
        if (!empty($validated['parent_id'])) {
            $parent = Post::query()->select(['id', 'parent_id'])->findOrFail($validated['parent_id']);

            if (!is_null($parent->parent_id)) {
                return response()->json([
                    'message' => 'MVP: Reply je povolený iba na root post (nie reply na reply).',
                ], 422);
            }
        }

        $data = [
            'user_id' => $user->id,
            'content' => $validated['content'],
            'parent_id' => $validated['parent_id'] ?? null,
        ];

        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');

            // uloženie do storage/app/public/posts/...
            $path = $file->store('posts', 'public');

            $data['attachment_path'] = $path;
            $data['attachment_mime'] = $file->getClientMimeType();
            $data['attachment_original_name'] = $file->getClientOriginalName();
            $data['attachment_size'] = $file->getSize();
        }

        $post = Post::create($data);

        $post->load([
            'user:id,name,email,location,bio,is_admin',
        ]);

        return response()->json($post, 201);
    }
}
