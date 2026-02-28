<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserNotificationPreferencesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'iss_alerts' => ['required', 'boolean'],
            'good_conditions_alerts' => ['required', 'boolean'],
        ];
    }
}
