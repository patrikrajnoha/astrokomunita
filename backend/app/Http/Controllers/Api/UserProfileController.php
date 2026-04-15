<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PollService;
use App\Services\PostPayloadService;
use App\Support\PublicUserPayload;
use App\Models\User;
use Illuminate\Http\Request;

class UserProfileController extends Controller
{
    public function __construct(
        private readonly PollService $polls,
        private readonly PostPayloadService $payloads,
        private readonly PublicUserPayload $publicUsers,
    ) {
    }

    public function show(string $username)
    {
        $user = User::query()
            ->select([
                'id',
                'name',
                'username',
                'bio',
                'location',
                'is_admin',
                'is_bot',
                'role',
                'avatar_path',
                'cover_path',
                'avatar_mode',
                'avatar_color',
                'avatar_icon',
                'avatar_seed',
            ])
            ->where('username', $username)
            ->first();

        if (!$user) {
            return response()->json(['message' => 'Používateľ sa nenašiel.'], 404);
        }

        return response()->json($this->publicUsers->fromUser($user));
    }

    public function posts(Request $request, string $username)
    {
        $user = User::query()->select(['id', 'username'])->where('username', $username)->first();
        if (!$user) {
            return response()->json(['message' => 'Používateľ sa nenašiel.'], 404);
        }

        $perPage = (int) $request->query('per_page', 20);
        if ($perPage < 1) {
            $perPage = 1;
        }
        if ($perPage > 50) {
            $perPage = 50;
        }

        $kind = $request->query('kind', 'roots');

        $viewer = $request->user() ?? $request->user('sanctum');
        $isAdmin = $viewer?->isAdmin() ?? false;

        $query = $user->posts()
            ->with(array_merge([
                'user:id,name,username,location,bio,is_admin,is_bot,avatar_path,avatar_mode,avatar_color,avatar_icon,avatar_seed',
            ], $this->polls->pollRelations($viewer?->id), $this->polls->nestedPollRelations(['parent'], $viewer?->id)))
            ->withCount(['replies', 'likes'])
            ->latest();

        if ($viewer) {
            $query->withExists([
                'likes as liked_by_me' => fn ($q) => $q->where('user_id', $viewer->id),
                'bookmarkedBy as is_bookmarked' => fn ($q) => $q->where('user_id', $viewer->id),
            ]);
        }

        if ($kind === 'replies') {
            $query->whereNotNull('parent_id');
            $query->with([
                'parent.user:id,name,username,location,bio,is_admin,is_bot,avatar_path,avatar_mode,avatar_color,avatar_icon,avatar_seed',
            ]);
        } elseif ($kind === 'media') {
            $query->whereNotNull('attachment_path');
            $query->with([
                'parent.user:id,name,username,location,bio,is_admin,is_bot,avatar_path,avatar_mode,avatar_color,avatar_icon,avatar_seed',
            ]);
        } else {
            $query->whereNull('parent_id');
        }

        if (!$request->boolean('include_hidden') || !$isAdmin) {
            $query->publiclyVisible();
        }

        $paginator = $query->paginate($perPage)->withQueryString();

        return response()->json(
            $this->payloads->serializePaginator($paginator, $viewer)
        );
    }
}
