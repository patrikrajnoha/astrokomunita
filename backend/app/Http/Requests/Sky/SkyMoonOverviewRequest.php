<?php

namespace App\Http\Requests\Sky;

class SkyMoonOverviewRequest extends SkyContextRequest
{
    /**
     * @return array<string,mixed>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'date' => ['nullable', 'date_format:Y-m-d'],
        ]);
    }
}

