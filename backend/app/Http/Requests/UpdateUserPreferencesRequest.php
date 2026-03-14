<?php

namespace App\Http\Requests;

use App\Enums\EventType;
use App\Enums\RegionScope;
use App\Support\SidebarSectionRegistry;
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
        $sidebarWidgetKeys = collect(SidebarSectionRegistry::sections())
            ->pluck('section_key')
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
            'sidebar_widget_keys' => ['nullable', 'array', 'max:3'],
            'sidebar_widget_keys.*' => ['string', Rule::in($sidebarWidgetKeys)],
            'sidebar_widget_overrides' => ['nullable', 'array'],
            'sidebar_widget_overrides.*' => ['array', 'max:3'],
            'sidebar_widget_overrides.*.*' => ['string', Rule::in($sidebarWidgetKeys)],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $rawOverrides = $this->input('sidebar_widget_overrides');
            if (!is_array($rawOverrides)) {
                return;
            }

            $validScopes = SidebarSectionRegistry::scopes();
            foreach (array_keys($rawOverrides) as $scope) {
                if (is_string($scope) && in_array($scope, $validScopes, true)) {
                    continue;
                }

                $validator->errors()->add(
                    'sidebar_widget_overrides',
                    'Konfiguracia obsahuje neplatny sidebar scope.'
                );
                break;
            }
        });
    }
}
