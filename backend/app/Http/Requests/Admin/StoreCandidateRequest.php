<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreCandidateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->isAdmin();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:2000'],
            'event_type' => ['required', 'string', 'in:meteor_shower,eclipse,comet,planetary,aurora,other'],
            'starts_at' => ['required', 'date', 'after:now'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'source' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Get custom error messages for validation rules.
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Názov kandidáta je povinný.',
            'title.max' => 'Názov kandidáta môže mať maximálne 255 znakov.',
            'description.required' => 'Popis kandidáta je povinný.',
            'description.max' => 'Popis kandidáta môže mať maximálne 2000 znakov.',
            'event_type.required' => 'Typ eventu je povinný.',
            'event_type.in' => 'Neplatný typ eventu.',
            'starts_at.required' => 'Dátum začiatku je povinný.',
            'starts_at.after' => 'Dátum začiatku musí byť v budúcnosti.',
            'ends_at.after' => 'Dátum konca musí byť po dátume začiatku.',
        ];
    }
}
