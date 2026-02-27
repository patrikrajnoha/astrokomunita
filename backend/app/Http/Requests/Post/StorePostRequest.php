<?php

namespace App\Http\Requests\Post;

use App\Enums\PostAuthorKind;
use App\Enums\PostBotIdentity;
use App\Enums\PostFeedKey;
use App\Models\User;
use App\Services\PostService;
use App\Services\PollService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
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
            'content' => [
                'required',
                'string',
                'min:1',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $max = $this->isKozmoPayload()
                        ? PostService::KOZMO_CONTENT_MAX
                        : PostService::USER_CONTENT_MAX;

                    if (mb_strlen((string) $value) <= $max) {
                        return;
                    }

                    $fail(sprintf('Post content may not be greater than %d characters.', $max));
                },
            ],
            'attachment' => ['nullable', 'file', 'max:' . $maxKb, 'mimes:' . $mimes],
            'feed_key' => ['sometimes', 'string', Rule::in([PostFeedKey::COMMUNITY->value, PostFeedKey::ASTRO->value])],
            'author_kind' => ['sometimes', 'string', Rule::in([PostAuthorKind::USER->value, PostAuthorKind::BOT->value])],
            'bot_identity' => ['nullable', 'string', Rule::in([PostBotIdentity::KOZMO->value, PostBotIdentity::STELA->value])],
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
            'gif' => ['nullable', 'array'],
            'gif.id' => ['required_with:gif', 'string', 'max:120'],
            'gif.title' => ['nullable', 'string', 'max:255'],
            'gif.preview_url' => ['required_with:gif', 'url', 'max:2000'],
            'gif.original_url' => ['required_with:gif', 'url', 'max:2000'],
            'gif.width' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'gif.height' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'event_id' => ['nullable', 'integer', 'exists:events,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'content.required' => 'Post content is required.',
            'content.min' => 'Post content must contain at least 1 character.',
            'feed_key.in' => 'Feed key must be community or astro.',
            'author_kind.in' => 'Author kind must be either user or bot.',
            'bot_identity.in' => 'Bot identity must be kozmo or stela.',
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
            $this->validateBotAuthoring($validator);
        });
    }

    public function postAttributes(): array
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

        $eventId = $this->validated('event_id');
        if ($eventId !== null) {
            $meta['event'] = [
                'event_id' => (int) $eventId,
                'attached_type' => 'event',
            ];
        }

        return [
            'feed_key' => $this->validated('feed_key'),
            'author_kind' => strtolower((string) ($this->validated('author_kind') ?? ($this->user()?->isBot() ? PostAuthorKind::BOT->value : PostAuthorKind::USER->value))),
            'bot_identity' => $this->validated('bot_identity'),
            'meta' => $meta !== [] ? $meta : null,
        ];
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

        if (is_array($this->validated('gif'))) {
            throw ValidationException::withMessages([
                'gif' => 'Poll a GIF sa nedaju kombinovat.',
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

        if ($this->hasFile('attachment') && is_array($this->validated('gif'))) {
            throw ValidationException::withMessages([
                'attachment' => 'Priloha a GIF sa nedaju kombinovat.',
            ]);
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

    private function validateBotAuthoring(Validator $validator): void
    {
        $requestedKind = strtolower((string) ($this->input('author_kind') ?? ($this->user()?->isBot() ? PostAuthorKind::BOT->value : PostAuthorKind::USER->value)));
        $feedKey = strtolower(trim((string) ($this->input('feed_key') ?? '')));
        $identity = strtolower(trim((string) ($this->input('bot_identity') ?? '')));

        if ($requestedKind !== PostAuthorKind::BOT->value) {
            if ($feedKey === PostFeedKey::ASTRO->value) {
                $validator->errors()->add('author_kind', 'Astro feed accepts only bot root posts.');
            }

            if ($identity !== '') {
                $validator->errors()->add('bot_identity', 'Bot identity can only be used for bot posts.');
            }

            return;
        }

        $user = $this->user();
        if (!$user || (!$user->isBot() && !$user->isAdmin())) {
            $validator->errors()->add('author_kind', 'Bot posts can only be created by bot or admin users.');
            return;
        }

        if ($feedKey !== '' && $feedKey !== PostFeedKey::ASTRO->value) {
            $validator->errors()->add('feed_key', 'Bot posts must target astro feed.');
        }

        if ($identity !== '') {
            return;
        }

        if ($this->inferBotIdentityFromUser($user) !== null) {
            return;
        }

        $validator->errors()->add('bot_identity', 'Bot identity is required for bot posts.');
    }

    private function isKozmoPayload(): bool
    {
        $requestedKind = strtolower((string) ($this->input('author_kind') ?? ($this->user()?->isBot() ? PostAuthorKind::BOT->value : PostAuthorKind::USER->value)));
        if ($requestedKind !== PostAuthorKind::BOT->value) {
            return false;
        }

        $identity = strtolower(trim((string) ($this->input('bot_identity') ?? '')));
        if ($identity === PostBotIdentity::KOZMO->value) {
            return true;
        }

        $inferred = $this->inferBotIdentityFromUser($this->user());

        return $inferred === PostBotIdentity::KOZMO->value;
    }

    private function inferBotIdentityFromUser(?User $user): ?string
    {
        if (!$user) {
            return null;
        }

        $username = strtolower(trim((string) $user->username));
        if ($username === PostBotIdentity::KOZMO->value) {
            return PostBotIdentity::KOZMO->value;
        }

        if ($username === PostBotIdentity::STELA->value || $username === 'astrobot') {
            return PostBotIdentity::STELA->value;
        }

        $email = strtolower(trim((string) $user->email));
        if (str_contains($email, PostBotIdentity::KOZMO->value)) {
            return PostBotIdentity::KOZMO->value;
        }

        if (str_contains($email, PostBotIdentity::STELA->value) || str_contains($email, 'astrobot')) {
            return PostBotIdentity::STELA->value;
        }

        return null;
    }
}
