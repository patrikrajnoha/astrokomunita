<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EventIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['nullable', 'string', 'max:50'],
            'from' => ['nullable', 'date', 'required_with:to'],
            'to'   => ['nullable', 'date', 'required_with:from'],
            'q'    => ['nullable', 'string', 'max:200'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
