<?php

namespace App\Http\Requests\Post;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;

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
            'gif' => ['nullable', 'array'],
            'gif.id' => ['required_with:gif', 'string', 'max:120'],
            'gif.title' => ['nullable', 'string', 'max:255'],
            'gif.preview_url' => ['required_with:gif', 'url', 'max:2000'],
            'gif.original_url' => ['required_with:gif', 'url', 'max:2000'],
            'gif.width' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'gif.height' => ['nullable', 'integer', 'min:1', 'max:10000'],
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

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $file = $this->file('attachment');
            if (!$file) {
                return;
            }

            $mime = strtolower(trim((string) ($file->getMimeType() ?: $file->getClientMimeType())));
            if (!str_starts_with($mime, 'image/')) {
                return;
            }

            $allowed = array_map(
                fn (mixed $value): string => strtolower(trim((string) $value)),
                (array) config('media.post_image_allowed_mimes', [])
            );

            if (!in_array($mime, $allowed, true)) {
                $validator->errors()->add('attachment', 'Unsupported image format.');
                return;
            }

            $path = $file->getRealPath();
            if (!$path) {
                return;
            }

            $dimensions = @getimagesize($path);
            if (!is_array($dimensions)) {
                return;
            }

            $maxPixels = (int) config('media.post_image_max_pixels', 10000);
            $width = isset($dimensions[0]) ? (int) $dimensions[0] : 0;
            $height = isset($dimensions[1]) ? (int) $dimensions[1] : 0;

            if ($maxPixels > 0 && ($width > $maxPixels || $height > $maxPixels)) {
                $validator->errors()->add('attachment', sprintf('Image dimensions cannot exceed %d px.', $maxPixels));
            }
        });
    }

    public function replyAttributes(): array
    {
        $meta = [];

        $gif = $this->validated('gif');
        if (is_array($gif)) {
            $meta['gif'] = [
                'id' => (string) ($gif['id'] ?? ''),
                'title' => trim((string) ($gif['title'] ?? '')),
                'preview_url' => (string) ($gif['preview_url'] ?? ''),
                'original_url' => (string) ($gif['original_url'] ?? ''),
                'width' => isset($gif['width']) ? (int) $gif['width'] : null,
                'height' => isset($gif['height']) ? (int) $gif['height'] : null,
            ];
        }

        return [
            'meta' => $meta !== [] ? $meta : null,
        ];
    }

    protected function passedValidation(): void
    {
        if ($this->hasFile('attachment') && is_array($this->validated('gif'))) {
            throw ValidationException::withMessages([
                'attachment' => 'Priloha a GIF sa nedaju kombinovat.',
            ]);
        }
    }
}
