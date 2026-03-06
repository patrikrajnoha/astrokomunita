<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateAvatarPreferencesRequest;
use App\Models\Report;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\Storage\MediaStorageService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class AdminUserController extends Controller
{
    private const PROFILE_MEDIA_UPLOAD_MAX_KB = 20480;

    public function __construct(
        private readonly NotificationService $notifications,
        private readonly MediaStorageService $mediaStorage,
    )
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
                'bio',
                'role',
                'is_bot',
                'is_banned',
                'banned_at',
                'ban_reason',
                'is_active',
                'avatar_path',
                'cover_path',
                'avatar_mode',
                'avatar_color',
                'avatar_icon',
                'avatar_seed',
            ])
            ->when(trim((string) $request->query('search', '')) !== '', function ($query) use ($request) {
                $term = '%'.trim((string) $request->query('search', '')).'%';
                $query->where(function ($nested) use ($term): void {
                    $nested->where('name', 'like', $term)
                        ->orWhere('username', 'like', $term)
                        ->orWhere('email', 'like', $term);
                });
            })
            ->orderByDesc('id')
            ->paginate($perPage);
        $users->getCollection()->each->makeVisible('email');

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
                'bio',
                'role',
                'is_bot',
                'is_banned',
                'banned_at',
                'ban_reason',
                'is_active',
                'avatar_path',
                'cover_path',
                'avatar_mode',
                'avatar_color',
                'avatar_icon',
                'avatar_seed',
                'created_at',
            ])
            ->findOrFail($id);
        $user->makeVisible('email');

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
        $user->avatar_mode = 'image';
        $user->avatar_color = null;
        $user->avatar_icon = null;
        $user->avatar_seed = null;
        $user->cover_path = null;
        $user->save();

        return response()->json($this->mapUser($user));
    }

    public function updateRole(Request $request, User $user)
    {
        $this->ensureAllowed($request, 'updateRole', $user);

        if ($user->isAdmin() || $user->isBot()) {
            return response()->json([
                'message' => 'Role change is not allowed for this account.',
            ], 422);
        }

        $validated = $request->validate([
            'role' => ['required', 'string', Rule::in([User::ROLE_USER, User::ROLE_EDITOR])],
        ]);

        $nextRole = (string) $validated['role'];
        if ($nextRole === $user->role) {
            return response()->json($this->mapUser($user));
        }

        $user->forceFill([
            'role' => $nextRole,
            'is_admin' => false,
        ])->save();

        return response()->json($this->mapUser($user));
    }

    public function updateProfile(Request $request, User $user)
    {
        $this->ensureAllowed($request, 'updateProfile', $user);

        $rules = [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'bio' => ['nullable', 'string', 'max:160'],
            // Media paths are managed through dedicated bot media endpoints only.
            'avatar_path' => ['prohibited'],
            'cover_path' => ['prohibited'],
        ];

        $validated = $request->validate($rules);

        if ($validated === []) {
            return response()->json($this->mapUser($user));
        }

        $user->fill($validated);
        $user->save();

        return response()->json($this->mapUser($user));
    }

    public function uploadAvatar(Request $request, User $user)
    {
        return $this->uploadBotMedia($request, $user, 'avatar');
    }

    public function uploadCover(Request $request, User $user)
    {
        return $this->uploadBotMedia($request, $user, 'cover');
    }

    public function removeAvatar(Request $request, User $user)
    {
        $this->ensureAllowed($request, 'updateProfile', $user);
        $this->ensureBotTarget($user);

        $oldPath = (string) ($user->avatar_path ?? '');
        $user->avatar_path = null;
        $user->save();

        if ($oldPath !== '') {
            $this->mediaStorage->delete($oldPath);
        }

        return response()->json($this->mapUser($user->fresh()));
    }

    public function removeCover(Request $request, User $user)
    {
        $this->ensureAllowed($request, 'updateProfile', $user);
        $this->ensureBotTarget($user);

        $oldPath = (string) ($user->cover_path ?? '');
        $user->cover_path = null;
        $user->save();

        if ($oldPath !== '') {
            $this->mediaStorage->delete($oldPath);
        }

        return response()->json($this->mapUser($user->fresh()));
    }

    public function updateAvatarPreferences(UpdateAvatarPreferencesRequest $request, User $user)
    {
        $this->ensureAllowed($request, 'updateProfile', $user);
        $this->ensureBotTarget($user);

        $validated = $request->validated();
        $oldAvatarPath = null;

        $user->avatar_mode = $validated['avatar_mode'];
        if ($user->avatar_mode === 'generated' && $user->avatar_path) {
            $oldAvatarPath = (string) $user->avatar_path;
            $user->avatar_path = null;
        }

        if (array_key_exists('avatar_color', $validated)) {
            $user->avatar_color = $this->normalizeAvatarColor($validated['avatar_color']);
        }

        if (array_key_exists('avatar_icon', $validated)) {
            $user->avatar_icon = $this->normalizeAvatarIcon($validated['avatar_icon']);
        }

        if (array_key_exists('avatar_seed', $validated)) {
            $user->avatar_seed = $this->normalizeAvatarSeed($validated['avatar_seed']);
        }

        $user->save();

        if ($oldAvatarPath !== null) {
            $this->mediaStorage->delete($oldAvatarPath);
        }

        return response()->json($this->mapUser($user->fresh()));
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
        $mapped = $user->only([
            'id',
            'name',
            'username',
            'email',
            'bio',
            'role',
            'is_bot',
            'is_banned',
            'banned_at',
            'ban_reason',
            'is_active',
            'avatar_path',
            'cover_path',
            'avatar_mode',
            'avatar_color',
            'avatar_icon',
            'avatar_seed',
        ]);

        $mapped['avatar_url'] = $user->avatar_url;
        $mapped['cover_url'] = $user->cover_url;

        return $mapped;
    }

    private function uploadBotMedia(Request $request, User $user, string $type)
    {
        $this->ensureAllowed($request, 'updateProfile', $user);
        $this->ensureBotTarget($user);

        $maxKb = max((int) config('media.profile_upload_max_kb', self::PROFILE_MEDIA_UPLOAD_MAX_KB), 3072);
        $request->validate([
            'file' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.$maxKb],
        ]);

        $freshUser = $this->replaceUserMedia($user, $type, $request->file('file'));

        return response()->json($this->mapUser($freshUser));
    }

    private function replaceUserMedia(User $user, string $type, mixed $file): User
    {
        $path = $type === 'avatar'
            ? $this->mediaStorage->storeAvatar($file, (int) $user->id)
            : $this->mediaStorage->storeCover($file, (int) $user->id);

        $column = $type === 'avatar' ? 'avatar_path' : 'cover_path';
        $oldPath = $user->{$column};
        $user->{$column} = $path;
        if ($type === 'avatar') {
            $user->avatar_mode = 'image';
        }
        $user->save();

        if ($oldPath && $oldPath !== $path) {
            $this->mediaStorage->delete($oldPath);
        }

        return $user->fresh();
    }

    private function ensureBotTarget(User $user): void
    {
        if (! $user->isBot()) {
            throw new HttpResponseException(response()->json([
                'message' => 'Media upload endpoint is available only for bot accounts.',
            ], 422));
        }
    }

    private function normalizeAvatarSeed(mixed $seed): ?string
    {
        $value = trim((string) ($seed ?? ''));

        return $value === '' ? null : $value;
    }

    private function normalizeAvatarColor(mixed $value): ?int
    {
        $colors = array_values((array) config('avatar.colors', []));
        $maxIndex = max(count($colors) - 1, -1);
        if ($maxIndex < 0) {
            return null;
        }

        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            $index = (int) $value;
            if ($index >= 0 && $index <= $maxIndex) {
                return $index;
            }
        }

        $normalized = strtolower(trim((string) $value));
        foreach ($colors as $index => $hex) {
            if (strtolower(trim((string) $hex)) === $normalized) {
                return $index;
            }
        }

        throw ValidationException::withMessages([
            'avatar_color' => 'The selected avatar color is invalid.',
        ]);
    }

    private function normalizeAvatarIcon(mixed $value): ?int
    {
        $icons = array_values((array) config('avatar.icons', []));
        $maxIndex = max(count($icons) - 1, -1);
        if ($maxIndex < 0) {
            return null;
        }

        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            $index = (int) $value;
            if ($index >= 0 && $index <= $maxIndex) {
                return $index;
            }
        }

        $normalized = strtolower(trim((string) $value));
        foreach ($icons as $index => $icon) {
            if (strtolower(trim((string) $icon)) === $normalized) {
                return $index;
            }
        }

        throw ValidationException::withMessages([
            'avatar_icon' => 'The selected avatar icon is invalid.',
        ]);
    }
}
