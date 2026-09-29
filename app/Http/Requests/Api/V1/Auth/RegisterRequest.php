<?php

namespace App\Http\Requests\Api\V1\Auth;

use App\Support\Tenancy\SalonDomain;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->exists('domain')) {
            $this->merge([
                'domain' => SalonDomain::normalize($this->input('domain')),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'salon_name' => ['required', 'string', 'max:120'],
            'domain' => SalonDomain::rules(),
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:120', 'unique:users,email'],
            'phone' => ['required', 'string', 'regex:/^\+?[0-9\s\-()]{7,20}$/', Rule::unique('users', 'phone')],
            'affiliate_code' => ['nullable', 'string', 'max:40'],
            'referral_code' => ['nullable', 'string', 'max:50'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
            'terms' => ['accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return SalonDomain::messages('domain');
    }
}
