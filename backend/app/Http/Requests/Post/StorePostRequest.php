<?php

namespace App\Http\Requests\Post;

use App\Services\PollService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class StorePostRequest extends FormRequest
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
            'poll' => ['nullable', 'array'],
            'poll.options' => ['required_with:poll', 'array', 'min:2', 'max:4'],
            'poll.options.*' => ['required', 'string', 'min:1', 'max:25'],
            'poll.duration_preset' => ['nullable', 'in:5m,1h,1d,3d,7d'],
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
            'poll.options.*.max' => 'Each poll option may not be greater than 25 characters.',
        ];
    }

    protected function passedValidation(): void
    {
        $poll = $this->validated('poll');
        if (!is_array($poll)) {
            return;
        }

        $durationFields = [
            'duration_preset' => !empty($poll['duration_preset']),
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
}
