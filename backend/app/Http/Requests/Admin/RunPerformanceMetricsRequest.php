<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RunPerformanceMetricsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $allowedSources = config('admin.performance.allowed_bot_sources', ['nasa_rss_breaking']);

        return [
            'run' => ['required', 'string', Rule::in(['all', 'events_list', 'canonical', 'bot'])],
            'sample_size' => ['nullable', 'integer', 'min:1', 'max:500'],
            'bot_source' => ['nullable', 'string', Rule::in($allowedSources)],
            'mode' => ['nullable', 'string', Rule::in(['normal', 'no_cache'])],
        ];
    }
}

