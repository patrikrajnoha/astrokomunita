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
            'year' => ['nullable', 'integer', 'between:1900,2100'],
            'month' => ['nullable', 'integer', 'between:1,12', 'required_with:year'],
            'week' => ['nullable', 'integer', 'between:1,53', 'required_with:year'],
            'q'    => ['nullable', 'string', 'max:200'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
