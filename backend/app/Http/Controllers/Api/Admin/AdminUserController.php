<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class AdminUserController extends Controller
{
    public function index(Request $request)
    {
        $perPage = (int) $request->query('per_page', 20);
        if ($perPage < 1) {
            $perPage = 1;
        }
        if ($perPage > 50) {
            $perPage = 50;
        }

        $users = User::query()
            ->select(['id', 'name', 'email', 'role', 'is_banned', 'is_active'])
            ->orderByDesc('id')
            ->paginate($perPage);

        return response()->json($users);
    }

    public function ban(Request $request, int $id)
    {
        $user = User::findOrFail($id);
        $this->ensureNotSelf($request, $user);

        $user->is_banned = true;
        $user->save();

        return response()->json($this->mapUser($user));
    }

    public function unban(Request $request, int $id)
    {
        $user = User::findOrFail($id);
        $this->ensureNotSelf($request, $user);

        $user->is_banned = false;
        $user->save();

        return response()->json($this->mapUser($user));
    }

    public function deactivate(Request $request, int $id)
    {
        $user = User::findOrFail($id);
        $this->ensureNotSelf($request, $user);

        $user->is_active = false;
        $user->save();

        return response()->json($this->mapUser($user));
    }

    public function resetProfile(int $id)
    {
        $user = User::findOrFail($id);

        $user->bio = null;
        $user->location = null;
        $user->avatar_path = null;
        $user->cover_path = null;
        $user->save();

        return response()->json($this->mapUser($user));
    }

    private function ensureNotSelf(Request $request, User $target): void
    {
        $actor = $request->user();
        if ($actor && (int) $actor->id === (int) $target->id) {
            abort(403, 'Forbidden');
        }
    }

    private function mapUser(User $user): array
    {
        return $user->only([
            'id',
            'name',
            'email',
            'role',
            'is_banned',
            'is_active',
        ]);
    }
}
