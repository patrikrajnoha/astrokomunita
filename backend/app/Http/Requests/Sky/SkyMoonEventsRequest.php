<?php

namespace App\Http\Requests\Sky;

class SkyMoonEventsRequest extends SkyContextRequest
{
    /**
     * @return array<string,mixed>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'year' => ['nullable', 'integer', 'between:1700,2100'],
        ]);
    }
}

