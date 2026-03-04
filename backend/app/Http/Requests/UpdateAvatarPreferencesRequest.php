<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAvatarPreferencesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $colors = array_values((array) config('avatar.colors', []));
        $icons = array_values((array) config('avatar.icons', []));

        return [
            'avatar_mode' => ['required', 'string', Rule::in((array) config('avatar.modes', ['image', 'generated']))],
            'avatar_color' => ['nullable', $this->avatarAllowlistRule($colors, 'color')],
            'avatar_icon' => ['nullable', $this->avatarAllowlistRule($icons, 'icon')],
            'avatar_seed' => ['nullable', 'string', 'max:80'],
        ];
    }

    private function avatarAllowlistRule(array $allowlist, string $type): \Closure
    {
        return static function (string $attribute, mixed $value, \Closure $fail) use ($allowlist, $type): void {
            if ($value === null || $value === '') {
                return;
            }

            $maxIndex = count($allowlist) - 1;

            if (is_numeric($value)) {
                $index = (int) $value;
                if ($index >= 0 && $index <= $maxIndex) {
                    return;
                }

                $fail("The selected avatar {$type} is invalid.");
                return;
            }

            $normalized = strtolower(trim((string) $value));

            foreach ($allowlist as $allowed) {
                if (strtolower(trim((string) $allowed)) === $normalized) {
                    return;
                }
            }

            $fail("The selected avatar {$type} is invalid.");
        };
    }
}
