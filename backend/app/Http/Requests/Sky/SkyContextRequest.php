<?php

namespace App\Http\Requests\Sky;

use Illuminate\Foundation\Http\FormRequest;

abstract class SkyContextRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string,mixed>
     */
    public function rules(): array
    {
        return [
            'lat' => ['nullable', 'numeric', 'between:-90,90', 'required_with:lon'],
            'lon' => ['nullable', 'numeric', 'between:-180,180', 'required_with:lat'],
            'tz' => ['nullable', 'string', 'max:64'],
        ];
    }
}
