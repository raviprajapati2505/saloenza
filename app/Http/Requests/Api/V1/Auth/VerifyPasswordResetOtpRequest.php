<?php

namespace App\Http\Requests\Api\V1\Auth;

use App\Support\AuthIdentifier;
use Illuminate\Foundation\Http\FormRequest;

class VerifyPasswordResetOtpRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'login' => $this->filled('login')
                ? AuthIdentifier::normalize((string) $this->input('login'))
                : null,
            'otp' => $this->filled('otp') ? trim((string) $this->input('otp')) : null,
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
            'otp' => ['required', 'digits:4'],
        ];
    }
}
