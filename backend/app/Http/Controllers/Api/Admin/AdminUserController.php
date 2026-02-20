<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Report;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AdminUserController extends Controller
{
    public function __construct(private readonly NotificationService $notifications)
    {
    }

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
            ->select([
                'id',
                'name',
                'username',
                'email',
                'role',
                'is_banned',
                'banned_at',
                'ban_reason',
                'is_active',
                'avatar_path',
            ])
            ->orderByDesc('id')
            ->paginate($perPage);

        return response()->json($users);
    }

    public function show(int $id)
    {
        $user = User::query()
            ->select([
                'id',
                'name',
                'username',
                'email',
                'role',
                'is_banned',
                'banned_at',
                'ban_reason',
                'is_active',
                'avatar_path',
                'created_at',
            ])
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

    public function ban(Request $request, User $user)
    {
        $this->ensureAllowed($request, 'ban', $user);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $user->is_banned = true;
        $user->banned_at = now();
        $user->ban_reason = trim((string) $validated['reason']);
        $user->save();

        $this->notifications->createAccountRestricted(
            (int) $user->id,
            (string) $user->ban_reason,
            (int) $request->user()->id
        );

        return response()->json($this->mapUser($user));
    }

    public function unban(Request $request, User $user)
    {
        $this->ensureAllowed($request, 'unban', $user);

        $user->is_banned = false;
        $user->banned_at = null;
        $user->ban_reason = null;
        $user->save();

        return response()->json($this->mapUser($user));
    }

    public function deactivate(Request $request, int $id)
    {
        $user = User::findOrFail($id);
        $this->ensureAllowed($request, 'deactivate', $user);

        $user->is_active = false;
        $user->save();

        return response()->json($this->mapUser($user));
    }

    public function resetProfile(Request $request, int $id)
    {
        $user = User::findOrFail($id);
        $this->ensureAllowed($request, 'resetProfile', $user);

        $user->bio = null;
        $user->location = null;
        $user->avatar_path = null;
        $user->cover_path = null;
        $user->save();

        return response()->json($this->mapUser($user));
    }

    private function ensureAllowed(Request $request, string $ability, User $target): void
    {
        $actor = $request->user();
        if ($actor && Gate::forUser($actor)->denies($ability, $target)) {
            abort(403, 'Forbidden');
        }
    }

    private function mapUser(User $user): array
    {
        return $user->only([
            'id',
            'name',
            'username',
            'email',
            'role',
            'is_banned',
            'banned_at',
            'ban_reason',
            'is_active',
            'avatar_path',
        ]);
    }
}
