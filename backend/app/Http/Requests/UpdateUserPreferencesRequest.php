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
        $interestKeys = collect(config('onboarding.interests', []))
            ->pluck('key')
            ->filter(static fn ($value) => is_string($value) && $value !== '')
            ->values()
            ->all();

        return [
            'event_types' => ['nullable', 'array'],
            'event_types.*' => ['string', Rule::in(EventType::values())],
            'region' => ['nullable', 'string', Rule::in(RegionScope::values())],
            'interests' => ['nullable', 'array'],
            'interests.*' => ['string', Rule::in($interestKeys)],
            'location_label' => ['nullable', 'string', 'max:255'],
            'location_place_id' => ['nullable', 'string', 'max:255'],
            'location_lat' => ['nullable', 'numeric', 'between:-90,90'],
            'location_lon' => ['nullable', 'numeric', 'between:-180,180'],
            'onboarding_completed_at' => ['nullable', 'date'],
            'bortle_class' => ['nullable', 'integer', 'between:1,9'],
        ];
    }
}
