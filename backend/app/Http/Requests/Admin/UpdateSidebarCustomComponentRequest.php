<?php

namespace App\Http\Requests\Admin;

use App\Models\SidebarCustomComponent;
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
        return [
            'name' => ['required', 'string', 'max:160'],
            'type' => ['required', Rule::in([SidebarCustomComponent::TYPE_SPECIAL_EVENT])],
            'is_active' => ['sometimes', 'boolean'],
            'config_json' => ['required', 'array'],
            'config_json.title' => ['required', 'string', 'max:120'],
            'config_json.description' => ['required', 'string', 'max:320'],
            'config_json.eventId' => ['nullable', 'integer', 'exists:events,id'],
            'config_json.buttonLabel' => ['required', 'string', 'max:90'],
            'config_json.buttonTarget' => ['nullable', 'string', 'max:255'],
            'config_json.imageUrl' => ['nullable', 'string', 'max:2048'],
            'config_json.icon' => ['nullable', 'string', 'max:80'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $config = $this->input('config_json', []);
        $allowedConfig = is_array($config) ? $config : [];

        $this->merge([
            'name' => $this->sanitizeString($this->input('name')),
            'type' => $this->input('type'),
            'config_json' => [
                'title' => $this->sanitizeString($allowedConfig['title'] ?? null),
                'description' => $this->sanitizeString($allowedConfig['description'] ?? null),
                'eventId' => $this->normalizeInt($allowedConfig['eventId'] ?? null),
                'buttonLabel' => $this->sanitizeString($allowedConfig['buttonLabel'] ?? null),
                'buttonTarget' => $this->sanitizeString($allowedConfig['buttonTarget'] ?? null),
                'imageUrl' => $this->sanitizeString($allowedConfig['imageUrl'] ?? null),
                'icon' => $this->sanitizeString($allowedConfig['icon'] ?? null),
            ],
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

    private function normalizeInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && ctype_digit($value)) {
            return (int) $value;
        }

        return null;
    }
}

