<?php

namespace App\Http\Requests\Post;

use App\Services\PollService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;

class StorePostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $poll = $this->input('poll');
        if (!is_array($poll)) {
            return;
        }

        $options = $poll['options'] ?? null;
        if (!is_array($options)) {
            return;
        }

        $normalized = [];
        foreach ($options as $option) {
            if (is_array($option)) {
                $normalized[] = $option;
                continue;
            }

            $normalized[] = [
                'text' => $option,
            ];
        }

        $poll['options'] = $normalized;
        $this->merge(['poll' => $poll]);
    }

    public function rules(): array
    {
        $mimes = implode(',', (array) config('media.post_attachment_mimes', []));
        $maxKb = (int) config('media.post_attachment_max_kb', 10240);
        $pollImageMaxKb = (int) config('media.poll_option_image_max_kb', 5120);

        return [
            'content' => ['required', 'string', 'min:1', 'max:2000'],
            'attachment' => ['nullable', 'file', 'max:' . $maxKb, 'mimes:' . $mimes],
            'poll' => ['nullable', 'array'],
            'poll.options' => ['required_with:poll', 'array', 'min:2', 'max:4'],
            'poll.options.*.text' => ['required', 'string', 'min:1', 'max:25'],
            'poll.options.*.image' => ['nullable', 'file', 'image', 'max:' . $pollImageMaxKb],
            'poll.duration_preset' => ['nullable', 'in:5m,1h,1d,3d,7d'],
            'poll.duration_seconds' => [
                'nullable',
                'integer',
                'min:' . PollService::MIN_DURATION_SECONDS,
                'max:' . PollService::MAX_DURATION_SECONDS,
            ],
            'poll.ends_in_seconds' => [
                'nullable',
                'integer',
                'min:' . PollService::MIN_DURATION_SECONDS,
                'max:' . PollService::MAX_DURATION_SECONDS,
            ],
            'poll.ends_at' => ['nullable', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'content.required' => 'Post content is required.',
            'content.min' => 'Post content must contain at least 1 character.',
            'content.max' => 'Post content may not be greater than 2000 characters.',
            'poll.options.min' => 'Poll must have at least 2 options.',
            'poll.options.max' => 'Poll can have at most 4 options.',
            'poll.options.*.text.max' => 'Each poll option may not be greater than 25 characters.',
            'poll.options.*.image.image' => 'Poll option image must be a valid image file.',
            'poll.options.*.image.max' => 'Poll option image may not be greater than 5 MB.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->validateAttachmentImageConstraints($validator);
        });
    }

    protected function passedValidation(): void
    {
        $poll = $this->validated('poll');
        if (!is_array($poll)) {
            return;
        }

        if ($this->hasFile('attachment')) {
            throw ValidationException::withMessages([
                'attachment' => 'Poll a prilohy sa nedaju kombinovat.',
            ]);
        }

        $durationFields = [
            'duration_preset' => !empty($poll['duration_preset']),
            'duration_seconds' => array_key_exists('duration_seconds', $poll) && $poll['duration_seconds'] !== null,
            'ends_in_seconds' => array_key_exists('ends_in_seconds', $poll) && $poll['ends_in_seconds'] !== null,
            'ends_at' => !empty($poll['ends_at']),
        ];

        if (count(array_filter($durationFields)) > 1) {
            throw ValidationException::withMessages([
                'poll' => 'Use only one poll duration input.',
            ]);
        }

        if (!empty($poll['ends_at'])) {
            $endsAt = Carbon::parse((string) $poll['ends_at']);
            $min = now()->addSeconds(PollService::MIN_DURATION_SECONDS);
            $max = now()->addSeconds(PollService::MAX_DURATION_SECONDS);

            if ($endsAt->lt($min) || $endsAt->gt($max)) {
                throw ValidationException::withMessages([
                    'poll.ends_at' => 'Poll end must be between 5 minutes and 7 days from now.',
                ]);
            }
        }
    }

    private function validateAttachmentImageConstraints(Validator $validator): void
    {
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
    }
}
