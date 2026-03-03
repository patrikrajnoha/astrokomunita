<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Storage\MediaStorageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller
{
    public function __construct(
        private readonly MediaStorageService $mediaStorage,
    ) {
    }

    public function update(Request $request)
    {
        $user = $request->user();
        $rawEmail = $request->input('email');
        if (is_string($rawEmail) && trim($rawEmail) === '') {
            $request->request->remove('email');
        }
        $rawName = $request->input('name');
        if (is_string($rawName) && trim($rawName) === '') {
            $request->request->remove('name');
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
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
        ]);

        $supportsLocationLabel = Schema::hasColumn('users', 'location_label');
        $payload = $validated;

        if (array_key_exists('email', $validated)) {
            $requestedEmail = mb_strtolower(trim((string) $validated['email']));
            $currentEmail = mb_strtolower(trim((string) ($user->email ?? '')));

            if ($requestedEmail !== '' && $requestedEmail !== $currentEmail) {
                return response()->json([
                    'message' => 'Use account email verification flow to change email.',
                    'error_code' => 'EMAIL_CHANGE_REQUIRES_VERIFICATION_FLOW',
                ], 422);
            }
        }

        unset($payload['email']);

        if (!$supportsLocationLabel) {
            unset($payload['location_label']);
        }

        $user->fill($payload);
        if ($supportsLocationLabel && array_key_exists('location_label', $validated)) {
            $label = trim((string) ($validated['location_label'] ?? ''));
            $user->location = $label !== '' ? Str::substr($label, 0, 60) : null;
        } elseif ($supportsLocationLabel && array_key_exists('location', $validated)) {
            $legacy = trim((string) ($validated['location'] ?? ''));
            $user->location_label = $legacy !== '' ? Str::substr($legacy, 0, 80) : null;
        }
        $user->save();

        return response()->json($user);
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

        return response()->json(['message' => 'Account deactivated.']);
    }

    public function uploadMedia(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'type' => ['required', Rule::in(['avatar', 'cover'])],
            'file' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:3072'],
        ]);

        $type = $validated['type'];
        $file = $request->file('file');
        $path = $type === 'avatar'
            ? $this->mediaStorage->storeAvatar($file, (int) $user->id)
            : $this->mediaStorage->storeCover($file, (int) $user->id);

        $column = $type === 'avatar' ? 'avatar_path' : 'cover_path';
        $oldPath = $user->{$column};

        $user->{$column} = $path;
        $user->save();

        if ($oldPath && $oldPath !== $path) {
            $this->mediaStorage->delete($oldPath);
        }

        return response()->json($user->fresh());
    }
}
