<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Models\AffiliatePartner;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreAffiliatePartnerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
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
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
            'display_name' => ['nullable', 'string', 'max:120'],
            'code' => ['nullable', 'string', 'max:40', Rule::unique('affiliate_partners', 'code')],
            'status' => ['nullable', Rule::in([
                AffiliatePartner::STATUS_ACTIVE,
                AffiliatePartner::STATUS_PENDING,
                AffiliatePartner::STATUS_SUSPENDED,
                AffiliatePartner::STATUS_REJECTED,
            ])],
            'onboarding_commission_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'renewal_commission_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'commission_lock_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'payout_method' => ['nullable', 'string', 'max:40'],
            'payout_details' => ['nullable', 'array'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
