<?php

namespace App\Http\Requests\Api\V1\Auth;

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

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'salon_name' => ['required', 'string', 'max:120'],
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:120', 'unique:users,email'],
            'phone' => ['required', 'string', 'regex:/^\+?[0-9\s\-()]{7,20}$/', Rule::unique('users', 'phone')],
            'affiliate_code' => ['nullable', 'string', 'max:40'],
            'referral_code' => ['nullable', 'string', 'max:50'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
            'terms' => ['accepted'],
        ];
    }
}
