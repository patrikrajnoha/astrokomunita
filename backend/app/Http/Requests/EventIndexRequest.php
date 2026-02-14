<?php

namespace App\Http\Requests;

use App\Enums\RegionScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'types' => ['nullable', 'string', 'max:500'],
            'region' => ['nullable', 'string', Rule::in(RegionScope::values())],
            'feed' => ['nullable', 'string', Rule::in(['all', 'mine'])],
            'from' => ['nullable', 'date', 'required_with:to'],
            'to'   => ['nullable', 'date', 'required_with:from'],
            'q'    => ['nullable', 'string', 'max:200'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
