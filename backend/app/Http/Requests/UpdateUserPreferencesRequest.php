<?php

namespace App\Http\Requests;

use App\Enums\EventType;
use App\Enums\RegionScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserPreferencesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'event_types' => ['nullable', 'array'],
            'event_types.*' => ['string', Rule::in(EventType::values())],
            'region' => ['required', 'string', Rule::in(RegionScope::values())],
        ];
    }
}
