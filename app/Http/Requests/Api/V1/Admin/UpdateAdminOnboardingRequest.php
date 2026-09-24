<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Models\Role;
use App\Models\SaloonBranch;
use App\Models\User;
use App\Support\Concerns\AuthorizesPermission;
use App\Support\PasswordRules;
use App\Support\Phone\PhoneNumber;
use App\Support\Role\RoleCodes;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAdminOnboardingRequest extends FormRequest
{
    use AuthorizesPermission;

    public function authorize(): bool
    {
        return $this->userCan('platform.salon_onboarding.update');
    }

    protected function prepareForValidation(): void
    {
        $saloon = $this->route('saloon');
        $saloonData = $this->input('saloon');

        if (is_array($saloonData) && isset($saloonData['business_name'])) {
            $saloonData['business_name'] = trim((string) $saloonData['business_name']);
            $this->merge(['saloon' => $saloonData]);
        }

        if ($saloon === null) {
            return;
        }

        $user = $this->input('user');
        if (is_array($user) && empty($user['id'])) {
            $ownerRoleId = Role::query()
                ->where('code', RoleCodes::SALON_FRANCHISE_OWNER)
                ->whereNull('saloon_id')
                ->value('id');

            $ownerId = User::query()
                ->where('saloon_id', $saloon->id)
                ->when($ownerRoleId !== null, fn ($query) => $query->where('role_id', $ownerRoleId))
                ->value('id');

            if ($ownerId !== null) {
                $user['id'] = $ownerId;
            }
        }

        if (is_array($user)) {
            if (array_key_exists('phone', $user)) {
                $user['phone'] = PhoneNumber::normalize($user['phone'] ?? null);
            }
            if (array_key_exists('whatsapp', $user)) {
                $user['whatsapp'] = PhoneNumber::normalize($user['whatsapp'] ?? null);
            }
            $this->merge(['user' => $user]);
        }

        $branch = $this->input('branch');
        if (is_array($branch) && empty($branch['id'])) {
            $branchId = SaloonBranch::query()
                ->where('saloon_id', $saloon->id)
                ->orderBy('id')
                ->value('id');

            if ($branchId !== null) {
                $branch['id'] = $branchId;
                $this->merge(['branch' => $branch]);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $saloon = $this->route('saloon');
        $saloonId = $saloon?->id;
        $userId = $this->input('user.id');
        $branchId = $this->input('branch.id');

        return [
            'saloon' => ['required', 'array'],
            'saloon.business_name' => ['required', 'string', 'max:120'],
            'saloon.payment_type' => ['required', 'string', Rule::in(['online', 'cash', 'card', 'upi', 'bank_transfer', 'other', 'monthly', 'quarterly', 'yearly', 'one-time', 'Monthly', 'Quarterly', 'Yearly', 'One-time'])],
            'saloon.payment_amount' => ['required', 'numeric', 'min:0'],
            'saloon.transaction_id' => ['nullable', 'string', 'max:120'],
            'saloon.is_active' => ['required', 'boolean'],
            'saloon.referral_code' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('saloons', 'referral_code')->ignore($saloonId),
            ],

            'branch' => ['required', 'array'],
            'branch.id' => ['nullable', 'integer', Rule::exists('saloon_branches', 'id')->where('saloon_id', $saloonId)],
            'branch.branch_name' => ['required', 'string', 'max:120'],
            'branch.business_address_1' => ['required', 'string', 'max:255'],
            'branch.business_address_2' => ['nullable', 'string', 'max:255'],
            'branch.city' => ['required', 'string', 'max:120'],
            'branch.state' => ['required', 'string', 'max:120'],
            'branch.area_pincode' => ['required', 'string', 'max:20'],
            'branch.country' => ['nullable', 'string', 'max:120'],
            'branch.is_active' => ['required', 'boolean'],

            'user' => ['required', 'array'],
            'user.id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('saloon_id', $saloonId)],
            'user.firstname' => ['required', 'string', 'max:120'],
            'user.lastname' => ['required', 'string', 'max:120'],
            'user.email' => [
                'required',
                'string',
                'email',
                'max:120',
                Rule::unique('users', 'email')->ignore($userId),
            ],
            'user.phone' => [
                'required',
                'string',
                'regex:'.PhoneNumber::E164_REGEX,
                Rule::unique('users', 'phone')->ignore($userId),
            ],
            'user.whatsapp' => [
                'nullable',
                'string',
                'regex:'.PhoneNumber::E164_REGEX,
            ],
            'user.password' => ['nullable', 'confirmed', PasswordRules::defaults()],
            'user.is_active' => ['required', 'boolean'],

            'service_products' => ['nullable', 'array'],
            'service_products.*.id' => ['nullable', 'integer', Rule::exists('salon_service_products', 'id')->where('saloon_id', $saloonId)],
            'service_products.*.service_id' => ['required_without:service_products.*.service_name', 'integer', 'exists:services,id'],
            'service_products.*.service_name' => ['required_without:service_products.*.service_id', 'string', 'max:120'],
            'service_products.*.product_id' => ['nullable', 'integer', 'exists:products,id'],
            'service_products.*.product_name' => ['nullable', 'string', 'max:120'],
            'service_products.*.price' => ['required', 'numeric', 'min:0'],
            'service_products.*.duration_minutes' => ['required', 'integer', 'min:1'],
            'service_products.*.is_active' => ['required', 'boolean'],

            'subscription_plan_id' => ['nullable', 'integer', 'exists:subscription_plans,id'],
            'start_trial' => ['sometimes', 'boolean'],
            'trial_days' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:365'],
            'affiliate_partner_id' => ['nullable', 'integer', 'exists:affiliate_partners,id'],
        ];
    }
}
