<?php

namespace App\Http\Requests\Post;

use Illuminate\Foundation\Http\FormRequest;

class ReplyPostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $mimes = implode(',', (array) config('media.post_attachment_mimes', []));
        $maxKb = (int) config('media.post_attachment_max_kb', 10240);

        return [
            'content' => ['required', 'string', 'min:1', 'max:2000'],
            'attachment' => ['nullable', 'file', 'max:' . $maxKb, 'mimes:' . $mimes],
        ];
    }

    public function messages(): array
    {
        return [
            'content.required' => 'Reply content is required.',
            'content.min' => 'Reply content must contain at least 1 character.',
            'content.max' => 'Reply content may not be greater than 2000 characters.',
        ];
    }
}
