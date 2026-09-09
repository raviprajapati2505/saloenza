<?php

namespace App\Http\Requests\Api\V1\Auth;

use App\Support\AuthIdentifier;
use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'login' => $this->filled('login')
                ? AuthIdentifier::normalize((string) $this->input('login'))
                : null,
        ]);
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
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
            'password' => ['required', 'string', 'min:8', 'max:255'],
            'device_name' => ['sometimes', 'string', 'max:120'],
        ];
    }
}
