<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateAvatarPreferencesRequest;
use App\Models\Report;
use App\Models\User;
use App\Services\Moderation\UploadImageModerationGuard;
use App\Services\NotificationService;
use App\Services\Storage\MediaStorageService;
use App\Support\BotAvatarResolver;
use App\Support\ProfanityFilter;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\UploadedFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdminUserController extends Controller
{
    private const PROFILE_MEDIA_UPLOAD_MAX_KB = 20480;
    private const ADMIN_STATS_CACHE_KEY = 'admin:stats:v1';

    public function __construct(
        private readonly NotificationService $notifications,
        private readonly MediaStorageService $mediaStorage,
        private readonly UploadImageModerationGuard $uploadImageModeration,
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

        $search = trim((string) $request->query('search', ''));
        $role = strtolower(trim((string) $request->query('role', '')));
        $status = strtolower(trim((string) $request->query('status', '')));
        $includeBots = filter_var(
            $request->query('include_bots', true),
            FILTER_VALIDATE_BOOLEAN,
            FILTER_NULL_ON_FAILURE
        );
        $includeBots = $includeBots ?? true;

        if (!in_array($role, [User::ROLE_ADMIN, User::ROLE_EDITOR, User::ROLE_USER, User::ROLE_BOT], true)) {
            $role = '';
        }
        if (!in_array($status, ['active', 'banned', 'inactive'], true)) {
            $status = '';
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
            ->when(!$includeBots, function ($query): void {
                $query
                    ->where(function ($botFlagQuery): void {
                        $botFlagQuery
                            ->where('is_bot', false)
                            ->orWhereNull('is_bot');
                    })
                    ->where(function ($roleQuery): void {
                        $roleQuery
                            ->whereNull('role')
                            ->orWhere('role', '!=', User::ROLE_BOT);
                    });
            })
            ->when($search !== '', function ($query) use ($search): void {
                $term = '%'.$search.'%';
                $query->where(function ($nested) use ($term): void {
                    $nested->where('name', 'like', $term)
                        ->orWhere('username', 'like', $term)
                        ->orWhere('email', 'like', $term);
                });
            })
            ->when($role !== '', function ($query) use ($role): void {
                $query->where('role', $role);
            })
            ->when($status !== '', function ($query) use ($status): void {
                if ($status === 'active') {
                    $query->where('is_active', true)->where('is_banned', false);
                    return;
                }

                if ($status === 'banned') {
                    $query->where('is_active', true)->where('is_banned', true);
                    return;
                }

                $query->where('is_active', false);
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
        $this->forgetAdminStatsCache();

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
        $this->forgetAdminStatsCache();

        return response()->json($this->mapUser($user));
    }

    public function deactivate(Request $request, int $id)
    {
        $user = User::findOrFail($id);
        $this->ensureAllowed($request, 'deactivate', $user);

        $user->is_active = false;
        $user->save();
        $this->forgetAdminStatsCache();

        return response()->json($this->mapUser($user));
    }

    public function reactivate(Request $request, int $id)
    {
        $user = User::findOrFail($id);
        $this->ensureAllowed($request, 'reactivate', $user);

        $user->is_active = true;
        $user->save();
        $this->forgetAdminStatsCache();

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
        $this->forgetAdminStatsCache();

        return response()->json($this->mapUser($user));
    }

    public function updateRole(Request $request, User $user)
    {
        $this->ensureAllowed($request, 'updateRole', $user);

        if ($user->isAdmin() || $user->isBot()) {
            return response()->json([
                'message' => 'Zmena roly pre tento ucet nie je povolena.',
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
        $this->forgetAdminStatsCache();

        return response()->json($this->mapUser($user));
    }

    public function updateProfile(Request $request, User $user)
    {
        $this->ensureAllowed($request, 'updateProfile', $user);

        $rules = [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (ProfanityFilter::containsBlockedWord((string) $value)) {
                        $fail('Meno obsahuje nepovoleny vyraz.');
                    }
                },
            ],
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

        $username = (string) ($user->username ?? '');
        $oldPath = (string) ($user->avatar_path ?? '');
        $user->avatar_path = null;
        $user->save();

        if ($oldPath !== '' && !BotAvatarResolver::isValidBotAvatarPath($oldPath, $username)) {
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

        $requestedPath = array_key_exists('avatar_path', $validated)
            ? (string) ($validated['avatar_path'] ?? '')
            : null;
        $resolvedBotPath = BotAvatarResolver::resolveBotAvatarPath($user, $requestedPath);
        if ($resolvedBotPath === null || !BotAvatarResolver::isValidBotAvatarPath($resolvedBotPath, (string) $user->username)) {
            throw ValidationException::withMessages([
                'avatar_path' => 'The selected bot avatar is invalid.',
            ]);
        }

        $previousPath = (string) ($user->avatar_path ?? '');
        $user->avatar_mode = 'image';
        $user->avatar_path = $resolvedBotPath;
        $user->avatar_color = null;
        $user->avatar_icon = null;
        $user->avatar_seed = null;

        if ($previousPath !== '' && !BotAvatarResolver::isValidBotAvatarPath($previousPath, (string) $user->username)) {
            $oldAvatarPath = $previousPath;
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
            abort(403, 'Zakazane');
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

        if ($user->isBot()) {
            $mapped['avatar_path'] = BotAvatarResolver::resolveBotAvatarPath(
                $user,
                (string) ($mapped['avatar_path'] ?? '')
            );
            $mapped['avatar_mode'] = 'image';
            $mapped['avatar_color'] = null;
            $mapped['avatar_icon'] = null;
            $mapped['avatar_seed'] = null;
        }

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

        $file = $request->file('file');
        if (!$file instanceof UploadedFile) {
            throw ValidationException::withMessages([
                'file' => 'Invalid uploaded file.',
            ]);
        }

        $this->uploadImageModeration->assertUploadedFileAllowed($file, 'file', 'admin_profile_' . $type);
        $freshUser = $this->replaceUserMedia($user, $type, $file);

        return response()->json($this->mapUser($freshUser));
    }

    private function replaceUserMedia(User $user, string $type, UploadedFile $file): User
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
                'message' => 'Endpoint na upload media je dostupny iba pre bot ucty.',
            ], 422));
        }
    }

    private function forgetAdminStatsCache(): void
    {
        Cache::forget(self::ADMIN_STATS_CACHE_KEY);
    }
}

