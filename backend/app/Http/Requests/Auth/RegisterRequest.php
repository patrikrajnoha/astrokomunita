<?php

namespace App\Http\Requests\Auth;

use App\Support\UsernameRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'username' => UsernameRules::normalize($this->input('username')),
        ]);
    }

    public function rules(): array
    {
        $minDate = now()->subYears(13)->endOfDay();

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'username' => UsernameRules::validationRules(),
            'date_of_birth' => ['required', 'date', 'before_or_equal:' . $minDate->toDateString()],
        ];
    }

    public function messages(): array
    {
        return [
            'username.required' => 'Pouzivatelske meno je povinne.',
            'username.min' => 'Pouzivatelske meno musi mat aspon 3 znaky.',
            'username.max' => 'Pouzivatelske meno moze mat najviac 20 znakov.',
            'username.regex' => 'Pouzivatelske meno moze obsahovat iba male pismena, cisla a podciarkovnik a musi zacinat pismenom.',
            'username.unique' => 'Toto pouzivatelske meno je uz obsadene.',
            'date_of_birth.required' => 'Datum narodenia je povinny.',
            'date_of_birth.date' => 'Datum narodenia musi byt platny datum.',
            'date_of_birth.before_or_equal' => 'Musis mat aspon 13 rokov.',
        ];
    }
}
