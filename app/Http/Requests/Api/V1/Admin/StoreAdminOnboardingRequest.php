<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Support\Concerns\AuthorizesPermission;
use App\Support\PasswordRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAdminOnboardingRequest extends FormRequest
{
    use AuthorizesPermission;

    public function authorize(): bool
    {
        return $this->userCan('platform.salon_onboarding.create');
    }

    protected function prepareForValidation(): void
    {
        $saloon = $this->input('saloon');

        if (is_array($saloon) && isset($saloon['business_name'])) {
            $saloon['business_name'] = trim((string) $saloon['business_name']);
            $this->merge(['saloon' => $saloon]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'saloon' => ['required', 'array'],
            'saloon.business_name' => ['required', 'string', 'max:120'],
            'saloon.payment_type' => ['required', 'string', Rule::in(['online', 'cash', 'card', 'upi', 'bank_transfer', 'other', 'monthly', 'quarterly', 'yearly', 'one-time', 'Monthly', 'Quarterly', 'Yearly', 'One-time'])],
            'saloon.payment_amount' => ['required', 'numeric', 'min:0'],
            'saloon.transaction_id' => ['nullable', 'string', 'max:120'],
            'saloon.is_active' => ['required', 'boolean'],

            'branch' => ['required', 'array'],
            'branch.branch_name' => ['required', 'string', 'max:120'],
            'branch.business_address_1' => ['required', 'string', 'max:255'],
            'branch.business_address_2' => ['nullable', 'string', 'max:255'],
            'branch.city' => ['required', 'string', 'max:120'],
            'branch.state' => ['required', 'string', 'max:120'],
            'branch.area_pincode' => ['required', 'string', 'max:20'],
            'branch.country' => ['nullable', 'string', 'max:120'],
            'branch.is_active' => ['required', 'boolean'],

            'user' => ['required', 'array'],
            'user.firstname' => ['required', 'string', 'max:120'],
            'user.lastname' => ['required', 'string', 'max:120'],
            'user.email' => ['required', 'string', 'email', 'max:120', Rule::unique('users', 'email')],
            'user.phone' => ['required', 'string', 'regex:/^\+?[0-9\s\-()]{7,20}$/', Rule::unique('users', 'phone')],
            'user.password' => ['nullable', 'confirmed', PasswordRules::defaults()],
            'user.is_active' => ['required', 'boolean'],

            'service_products' => ['nullable', 'array'],
            'service_products.*.service_id' => ['required_without:service_products.*.service_name', 'integer', 'exists:services,id'],
            'service_products.*.service_name' => ['required_without:service_products.*.service_id', 'string', 'max:120'],
            'service_products.*.product_id' => ['nullable', 'integer', 'exists:products,id'],
            'service_products.*.product_name' => ['nullable', 'string', 'max:120'],
            'service_products.*.price' => ['required', 'numeric', 'min:0'],
            'service_products.*.duration_minutes' => ['required', 'integer', 'min:1'],
            'service_products.*.is_active' => ['required', 'boolean'],

            'subscription_plan_id' => ['nullable', 'integer', 'exists:subscription_plans,id'],
            'subscription_plan_slug' => ['nullable', 'string', 'max:80'],
            'start_trial' => ['sometimes', 'boolean'],
            'trial_days' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:365'],
            'affiliate_partner_id' => ['nullable', 'integer', 'exists:affiliate_partners,id'],
        ];
    }
}
