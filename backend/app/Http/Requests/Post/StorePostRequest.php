<?php

namespace App\Http\Requests\Post;

use Illuminate\Foundation\Http\FormRequest;

class StorePostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'content' => ['required', 'string', 'min:1', 'max:2000'],
            'attachment' => ['nullable', 'file', 'max:5120', 'mimes:jpg,jpeg,png,webp,gif,pdf,txt,doc,docx'],
        ];
    }

    public function messages(): array
    {
        return [
            'content.required' => 'Obsah prispevku je povinny.',
            'content.min' => 'Obsah prispevku musi mat aspon 1 znak.',
            'content.max' => 'Obsah prispevku moze mat maximalne 2000 znakov.',
        ];
    }
}
