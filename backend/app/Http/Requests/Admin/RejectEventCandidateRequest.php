<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class RejectEventCandidateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // rieši admin middleware
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ];
    }
}
