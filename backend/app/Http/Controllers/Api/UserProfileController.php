<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class UserProfileController extends Controller
{
    public function show(string $username)
    {
        $user = User::query()
            ->select(['id', 'name', 'username', 'bio', 'location', 'is_admin', 'avatar_path', 'cover_path'])
            ->where('username', $username)
            ->first();

        if (!$user) {
            return response()->json(['message' => 'User not found.'], 404);
        }

        return response()->json($user);
    }

    public function posts(Request $request, string $username)
    {
        $user = User::query()->select(['id', 'username'])->where('username', $username)->first();
        if (!$user) {
            return response()->json(['message' => 'User not found.'], 404);
        }

        $perPage = (int) $request->query('per_page', 20);
        if ($perPage < 1) {
            $perPage = 1;
        }
        if ($perPage > 50) {
            $perPage = 50;
        }

        $kind = $request->query('kind', 'roots');

        $viewer = $request->user();
        $isAdmin = $viewer?->isAdmin() ?? false;

        $query = $user->posts()
            ->with([
                'user:id,name,username,location,bio,is_admin,avatar_path',
            ])
            ->withCount(['replies', 'likes'])
            ->latest();

        if ($viewer) {
            $query->withExists([
                'likes as liked_by_me' => fn ($q) => $q->where('user_id', $viewer->id),
            ]);
        }

        if ($kind === 'replies') {
            $query->whereNotNull('parent_id');
            $query->with([
                'parent.user:id,name,username,location,bio,is_admin,avatar_path',
            ]);
        } elseif ($kind === 'media') {
            $query->whereNotNull('attachment_path');
            $query->with([
                'parent.user:id,name,username,location,bio,is_admin,avatar_path',
            ]);
        } else {
            $query->whereNull('parent_id');
        }

        if (!$request->boolean('include_hidden') || !$isAdmin) {
            $query->where('is_hidden', false);
        }

        return response()->json(
            $query->paginate($perPage)->withQueryString()
        );
    }
}
