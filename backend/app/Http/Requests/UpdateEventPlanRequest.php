<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEventPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'personal_note' => ['nullable', 'string', 'max:4000'],
            'reminder_at' => ['nullable', 'date'],
            'planned_time' => ['nullable', 'date'],
            'planned_location_label' => ['nullable', 'string', 'max:160'],
        ];
    }
}
