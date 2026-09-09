<?php

namespace App\Http\Requests\Api\V1\Public;

use App\Support\PasswordRules;
use Illuminate\Foundation\Http\FormRequest;

class ApplyAffiliatePartnerRequest extends FormRequest
{
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
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:120', 'unique:users,email'],
            'phone' => ['required', 'string', 'max:30', 'unique:users,phone'],
            'password' => ['required', 'confirmed', PasswordRules::defaults()],
            'display_name' => ['nullable', 'string', 'max:120'],
            'payout_method' => ['nullable', 'string', 'max:40'],
            'payout_details' => ['nullable', 'array'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'terms' => ['accepted'],
        ];
    }
}
