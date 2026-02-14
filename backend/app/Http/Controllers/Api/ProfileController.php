<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Storage\MediaStorageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
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

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],

            // ✅ nové polia
            'bio' => ['nullable', 'string', 'max:160'],
            'location' => ['nullable', 'string', 'max:60'],
        ]);

        $user->fill($validated);
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

        if ($user?->tokens()) {
            $user->tokens()->delete();
        }

        Auth::guard('web')->logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        $user->delete();

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
