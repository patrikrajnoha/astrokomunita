<?php

namespace App\Http\Requests\Observation;

use Illuminate\Foundation\Http\FormRequest;

class UpdateObservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $nullable = [
            'description',
            'event_id',
            'location_lat',
            'location_lng',
            'location_name',
            'visibility_rating',
            'equipment',
        ];

        $normalized = [];
        foreach ($nullable as $field) {
            if (!$this->has($field)) {
                continue;
            }

            $value = $this->input($field);
            if (is_string($value) && trim($value) === '') {
                $normalized[$field] = null;
            }
        }

        if ($normalized !== []) {
            $this->merge($normalized);
        }
    }

    public function rules(): array
    {
        $mimes = implode(',', (array) config('media.observation_image_mimes', ['jpg', 'jpeg', 'png', 'webp', 'gif']));
        $maxKb = (int) config('media.observation_image_max_kb', 20480);
        $maxCount = max(1, (int) config('media.observation_image_max_count', 6));

        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'observed_at' => ['sometimes', 'required', 'date'],
            'event_id' => ['nullable', 'integer', 'exists:events,id'],
            'location_lat' => ['nullable', 'numeric', 'between:-90,90'],
            'location_lng' => ['nullable', 'numeric', 'between:-180,180'],
            'location_name' => ['nullable', 'string', 'max:255'],
            'visibility_rating' => ['nullable', 'integer', 'between:1,5'],
            'equipment' => ['nullable', 'string', 'max:2000'],
            'is_public' => ['sometimes', 'boolean'],
            'images' => ['sometimes', 'array', 'max:' . $maxCount],
            'images.*' => ['required_with:images', 'file', 'image', 'mimes:' . $mimes, 'max:' . $maxKb],
            'remove_media_ids' => ['sometimes', 'array'],
            'remove_media_ids.*' => ['integer', 'distinct'],
        ];
    }

    public function messages(): array
    {
        return [
            'images.max' => 'Maximum :max images are allowed per observation.',
            'images.*.image' => 'Each uploaded file must be an image.',
            'images.*.mimes' => 'Allowed image formats are: :values.',
            'images.*.max' => 'Each image may be up to :max KB.',
        ];
    }
}
