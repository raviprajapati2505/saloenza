<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Models\AffiliatePartner;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAffiliatePartnerRequest extends FormRequest
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
        /** @var AffiliatePartner|null $partner */
        $partner = $this->route('affiliatePartner');

        return [
            'name' => ['sometimes', 'string', 'max:120'],
            'email' => ['sometimes', 'email', 'max:120', Rule::unique('users', 'email')->ignore($partner?->user_id)],
            'phone' => ['sometimes', 'string', 'max:30', Rule::unique('users', 'phone')->ignore($partner?->user_id)],
            'display_name' => ['sometimes', 'nullable', 'string', 'max:120'],
            'code' => ['sometimes', 'string', 'max:40', Rule::unique('affiliate_partners', 'code')->ignore($partner?->id)],
            'status' => ['sometimes', Rule::in([
                AffiliatePartner::STATUS_ACTIVE,
                AffiliatePartner::STATUS_PENDING,
                AffiliatePartner::STATUS_SUSPENDED,
                AffiliatePartner::STATUS_REJECTED,
            ])],
            'onboarding_commission_rate' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'renewal_commission_rate' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'commission_lock_days' => ['sometimes', 'integer', 'min:0', 'max:365'],
            'payout_method' => ['sometimes', 'nullable', 'string', 'max:40'],
            'payout_details' => ['sometimes', 'nullable', 'array'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
