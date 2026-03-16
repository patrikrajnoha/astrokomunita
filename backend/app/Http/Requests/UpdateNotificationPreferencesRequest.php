<?php

namespace App\Http\Requests;

use App\Models\NotificationPreference;
use Illuminate\Foundation\Http\FormRequest;

class UpdateNotificationPreferencesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $allowed = NotificationPreference::TYPE_KEYS;
        $rules = [
            'in_app' => [
                'required',
                'array',
                function (string $attribute, mixed $value, \Closure $fail) use ($allowed): void {
                    if (!is_array($value)) {
                        return;
                    }

                    $unknown = array_values(array_diff(array_keys($value), $allowed));
                    if ($unknown !== []) {
                        $fail('Neznámy typ notifikácie: ' . implode(', ', $unknown));
                    }
                },
            ],
            'email_enabled' => ['required', 'boolean'],
            'email' => [
                'nullable',
                'array',
                function (string $attribute, mixed $value, \Closure $fail) use ($allowed): void {
                    if (!is_array($value)) {
                        return;
                    }

                    $unknown = array_values(array_diff(array_keys($value), $allowed));
                    if ($unknown !== []) {
                        $fail('Neznámy email typ notifikácie: ' . implode(', ', $unknown));
                    }
                },
            ],
        ];

        foreach ($allowed as $key) {
            $rules["in_app.$key"] = ['required', 'boolean'];
            $rules["email.$key"] = ['nullable', 'boolean'];
        }

        return $rules;
    }
}
