<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateAvatarPreferencesRequest;
use App\Models\User;
use App\Services\Location\UserLocationService;
use App\Services\Moderation\UploadImageModerationGuard;
use App\Services\Storage\MediaStorageService;
use App\Support\ProfanityFilter;
use Illuminate\Http\UploadedFile;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller
{
    private const PROFILE_MEDIA_UPLOAD_MAX_KB = 20480;

    public function __construct(
        private readonly MediaStorageService $mediaStorage,
        private readonly UserLocationService $userLocationService,
        private readonly UploadImageModerationGuard $uploadImageModeration,
    ) {
    }

    public function update(Request $request)
    {
        $user = $request->user();
        $this->ensureBotProfileEditAllowed($user);
        $rawEmail = $request->input('email');
        if (is_string($rawEmail) && trim($rawEmail) === '') {
            $request->request->remove('email');
        }
        $rawName = $request->input('name');
        if (is_string($rawName) && trim($rawName) === '') {
            $request->request->remove('name');
        }

        $validated = $request->validate([
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
            'email' => [
                'sometimes',
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],

         
            'bio' => ['nullable', 'string', 'max:160'],
            'location' => ['nullable', 'string', 'max:60'],
            'location_label' => ['nullable', 'string', 'max:80'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'timezone' => ['nullable', 'string', 'max:64'],
            'location_source' => ['nullable', 'string', Rule::in(['preset', 'gps', 'manual'])],
        ]);

        $locationPayload = $this->extractLocationPayload($validated);
        $payload = Arr::except($validated, [
            'email',
            'location',
            'location_label',
            'latitude',
            'longitude',
            'timezone',
            'location_source',
        ]);

        if (array_key_exists('email', $validated)) {
            $requestedEmail = mb_strtolower(trim((string) $validated['email']));
            $currentEmail = mb_strtolower(trim((string) ($user->email ?? '')));

            if ($requestedEmail !== '' && $requestedEmail !== $currentEmail) {
                return response()->json([
                    'message' => 'Na zmenu e-mailu pouzite tok overenia e-mailu uctu.',
                    'error_code' => 'EMAIL_CHANGE_REQUIRES_VERIFICATION_FLOW',
                ], 422);
            }
        }

        DB::transaction(function () use ($user, $payload, $locationPayload): void {
            $user->fill($payload);
            $user->save();

            if ($locationPayload !== []) {
                $this->userLocationService->update($user, $locationPayload, true);
            }
        });

        return response()->json($user->fresh());
    }

    public function changePassword(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        if (!Hash::check($validated['current_password'], $user->password)) {
            return response()->json([
                'message' => 'Aktuálne heslo nie je správne.',
            ], 422);
        }

        $user->password = $validated['password']; // cast 'hashed' to zahashuje
        $user->save();

        return response()->json(['message' => 'Heslo bolo zmenené.']);
    }

    public function destroy(Request $request)
    {
        $user = $request->user();

        try {
            DB::transaction(function () use ($user): void {
                if ($user?->tokens()) {
                    $user->tokens()->delete();
                }

                $user->delete();
            });
        } catch (\Throwable $e) {
            Log::error('Failed to delete user account.', [
                'user_id' => $user?->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }

        Auth::guard('web')->logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->json(['message' => 'Ucet bol deaktivovany.']);
    }

    public function uploadMedia(Request $request)
    {
        $user = $request->user();
        $this->ensureBotProfileEditAllowed($user);
        $maxKb = max((int) config('media.profile_upload_max_kb', self::PROFILE_MEDIA_UPLOAD_MAX_KB), 3072);

        $validated = $request->validate([
            'type' => ['required', Rule::in(['avatar', 'cover'])],
            'file' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.$maxKb],
        ]);

        $type = $validated['type'];
        $file = $request->file('file');
        if (!$file instanceof UploadedFile) {
            throw ValidationException::withMessages([
                'file' => 'Invalid uploaded file.',
            ]);
        }

        $this->uploadImageModeration->assertUploadedFileAllowed($file, 'file', 'profile_' . $type);
        $freshUser = $this->replaceUserMedia($user, $type, $file);

        return response()->json($freshUser);
    }

    public function uploadAvatarImage(Request $request)
    {
        $user = $request->user();
        $this->ensureBotProfileEditAllowed($user);
        $maxKb = max((int) config('media.profile_upload_max_kb', self::PROFILE_MEDIA_UPLOAD_MAX_KB), 3072);
        $validated = $request->validate([
            'file' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.$maxKb],
        ]);

        $file = $request->file('file');
        if (!$file instanceof UploadedFile) {
            throw ValidationException::withMessages([
                'file' => 'Invalid uploaded file.',
            ]);
        }

        $this->uploadImageModeration->assertUploadedFileAllowed($file, 'file', 'profile_avatar');
        $freshUser = $this->replaceUserMedia($user, 'avatar', $file);

        return response()->json($freshUser);
    }

    public function removeAvatarImage(Request $request)
    {
        $user = $request->user();
        $this->ensureBotProfileEditAllowed($user);
        $oldPath = $user->avatar_path;
        $user->avatar_path = null;
        $user->save();

        if ($oldPath) {
            $this->mediaStorage->delete($oldPath);
        }

        return response()->json($user->fresh());
    }

    public function updateAvatar(UpdateAvatarPreferencesRequest $request)
    {
        $user = $request->user();
        $this->ensureBotProfileEditAllowed($user);
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

        return response()->json($user->fresh());
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

    /**
     * @param array<string,mixed> $validated
     * @return array<string,mixed>
     */
    private function extractLocationPayload(array $validated): array
    {
        $payload = [];
        $keys = [
            'location',
            'location_label',
            'latitude',
            'longitude',
            'timezone',
            'location_source',
        ];

        foreach ($keys as $key) {
            if (array_key_exists($key, $validated)) {
                $payload[$key] = $validated[$key];
            }
        }

        return $payload;
    }

    private function ensureBotProfileEditAllowed(User $user): void
    {
        if ($user->isBot() && ! $user->isAdmin()) {
            abort(403, 'Bot profily moze upravovat iba admin.');
        }
    }
}

