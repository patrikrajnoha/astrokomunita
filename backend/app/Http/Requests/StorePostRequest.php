<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePostRequest extends FormRequest
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
            'content' => ['required', 'string', 'max:2000'],
            'parent_id' => ['nullable', 'integer', 'exists:posts,id'],
            'attachment' => ['nullable', 'file', 'image', 'max:5120'], // 5MB
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
            'parent_id.exists' => 'Nadradený príspevok neexistuje.',
            'attachment.image' => 'Príloha musí byť obrázok.',
            'attachment.max' => 'Veľkosť prílohy nesmie presiahnuť 5MB.',
        ];
    }
}
