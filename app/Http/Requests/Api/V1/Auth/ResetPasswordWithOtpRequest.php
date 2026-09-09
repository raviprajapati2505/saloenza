<?php

namespace App\Http\Requests\Api\V1\Auth;

use App\Support\AuthIdentifier;
use App\Support\PasswordRules;
use Illuminate\Foundation\Http\FormRequest;

class ResetPasswordWithOtpRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'login' => $this->filled('login')
                ? AuthIdentifier::normalize((string) $this->input('login'))
                : null,
            'verification_token' => $this->filled('verification_token')
                ? trim((string) $this->input('verification_token'))
                : null,
        ]);
    }

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'login' => [
                'required',
                'string',
                'max:120',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $login = (string) $value;

                    if (! AuthIdentifier::isEmail($login) && ! AuthIdentifier::isPhone($login)) {
                        $fail('Please enter a valid email address or phone number.');
                    }
                },
            ],
            'verification_token' => ['required', 'uuid'],
            'new_password' => ['required', 'confirmed', PasswordRules::defaults()],
        ];
    }
}
