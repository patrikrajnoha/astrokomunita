<?php

namespace App\Http\Requests\Admin;

use App\Models\SidebarCustomComponent;
use App\Support\SidebarWidgetConfigSchema;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSidebarCustomComponentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $normalizedType = SidebarWidgetConfigSchema::normalizeTypeOrNull($this->input('type'));

        $baseRules = [
            'name' => ['required', 'string', 'max:160'],
            'type' => ['required', Rule::in(SidebarCustomComponent::acceptedInputTypes())],
            'is_active' => ['sometimes', 'boolean'],
            'config_json' => ['required', 'array'],
        ];

        if ($normalizedType === null) {
            return $baseRules;
        }

        return array_merge($baseRules, SidebarWidgetConfigSchema::validationRules($normalizedType));
    }

    protected function prepareForValidation(): void
    {
        $rawType = $this->sanitizeType($this->input('type'));
        $normalizedType = SidebarWidgetConfigSchema::normalizeType($rawType);
        $config = $this->input('config_json', []);
        $allowedConfig = is_array($config) ? $config : [];

        $this->merge([
            'name' => $this->sanitizeString($this->input('name')),
            'type' => $rawType,
            'config_json' => SidebarWidgetConfigSchema::normalizeConfig($normalizedType, $allowedConfig),
        ]);
    }

    private function sanitizeString(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $trimmed = trim(strip_tags($value));

        return $trimmed === '' ? null : $trimmed;
    }

    private function sanitizeType(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $trimmed = trim(strtolower($value));
        return $trimmed === '' ? null : $trimmed;
    }
}
