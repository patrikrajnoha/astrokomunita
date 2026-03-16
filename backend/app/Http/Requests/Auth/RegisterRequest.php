<?php

namespace App\Http\Requests\Auth;

use App\Services\Security\TurnstileService;
use App\Support\ProfanityFilter;
use App\Support\UsernameRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Rule;

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

        $turnstile = app(TurnstileService::class);
        if ($turnstile->isEnabled() && ! $turnstile->hasSecretKey()) {
            $turnstile->logMissingSecretWarningOnce();

            throw new HttpResponseException(response()->json([
                'message' => 'Bezpečnostné overenie je dočasne nedostupné.',
            ], 503));
        }
    }

    public function rules(): array
    {
        $minDate = now()->subYears(13)->endOfDay();

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (ProfanityFilter::containsBlockedWord((string) $value)) {
                        $fail('Meno obsahuje nepovoleny vyraz.');
                    }
                },
            ],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'username' => UsernameRules::validationRules(),
            'date_of_birth' => ['required', 'date', 'before_or_equal:' . $minDate->toDateString()],
            'turnstile_token' => [Rule::requiredIf((bool) config('services.turnstile.enabled')), 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'username.required' => 'Používateľské meno je povinné.',
            'username.min' => 'Používateľské meno musí mať aspoň 3 znaky.',
            'username.max' => 'Používateľské meno môže mať najviac 20 znakov.',
            'username.regex' => 'Používateľské meno môže obsahovať iba malé písmená, čísla a podčiarknik a musí začínať písmenom.',
            'username.unique' => 'Toto používateľské meno je už obsadené.',
            'date_of_birth.required' => 'Dátum narodenia je povinný.',
            'date_of_birth.date' => 'Dátum narodenia musí byť platný dátum.',
            'date_of_birth.before_or_equal' => 'Musíš mať aspoň 13 rokov.',
            'turnstile_token.required' => 'Overenie proti botom je povinné.',
            'turnstile_token.string' => 'Overenie proti botom je neplatné.',
        ];
    }
}
