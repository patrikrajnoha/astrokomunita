<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Report;
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

    public function show(int $id)
    {
        $user = User::query()
            ->select(['id', 'name', 'email', 'role', 'is_banned', 'is_active', 'created_at'])
            ->findOrFail($id);

        return response()->json($user);
    }

    public function reports(Request $request, int $id)
    {
        $user = User::findOrFail($id);

        $perPage = (int) $request->query('per_page', 20);
        if ($perPage < 1) {
            $perPage = 1;
        }
        if ($perPage > 50) {
            $perPage = 50;
        }

        $status = $request->query('status');
        $search = trim((string) $request->query('search', ''));

        $query = Report::query()
            ->with([
                'reporter:id,name',
                'target:id,content,user_id',
            ])
            ->whereHas('target', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            })
            ->orderByDesc('created_at');

        if ($status) {
            $query->where('status', $status);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('reason', 'like', '%' . $search . '%')
                    ->orWhere('message', 'like', '%' . $search . '%')
                    ->orWhereHas('reporter', function ($reporterQuery) use ($search) {
                        $reporterQuery->where('name', 'like', '%' . $search . '%');
                    });
            });
        }

        return response()->json($query->paginate($perPage));
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
