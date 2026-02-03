<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePostRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'content' => ['sometimes', 'required', 'string', 'max:2000'],
            'is_hidden' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Get custom error messages for validation rules.
     */
    public function messages(): array
    {
        return [
            'content.required' => 'Obsah príspevku je povinný.',
            'content.max' => 'Obsah príspevku môže mať maximálne 2000 znakov.',
        ];
    }
}
