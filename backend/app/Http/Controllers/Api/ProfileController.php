<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller
{
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
}
