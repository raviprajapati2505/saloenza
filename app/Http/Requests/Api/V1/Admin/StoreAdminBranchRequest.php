<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Support\Concerns\AuthorizesPermission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAdminBranchRequest extends FormRequest
{
    use AuthorizesPermission;

    protected function prepareForValidation(): void
    {
        $manager = $this->input('manager');

        if (! is_array($manager)) {
            return;
        }

        $this->merge([
            'manager' => array_merge($manager, [
                'firstname' => isset($manager['firstname']) ? trim((string) $manager['firstname']) : null,
                'lastname' => isset($manager['lastname']) ? trim((string) $manager['lastname']) : null,
                'email' => isset($manager['email']) ? strtolower(trim((string) $manager['email'])) : null,
                'phone' => isset($manager['phone']) ? trim((string) $manager['phone']) : null,
            ]),
        ]);
    }

    public function authorize(): bool
    {
        return $this->userCan('platform.branches.create') || $this->userIsSystemAdmin();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $saloonId = $this->input('saloon_id');

        return [
            'saloon_id' => ['required', 'integer', 'exists:saloons,id'],
            'branch_name' => ['required', 'string', 'max:120'],
            'business_address_1' => ['required', 'string', 'max:255'],
            'business_address_2' => ['nullable', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:120'],
            'state' => ['required', 'string', 'max:120'],
            'area_pincode' => ['required', 'string', 'max:20'],
            'country' => ['nullable', 'string', 'max:120'],
            'is_active' => ['required', 'boolean'],
            'template_branch_id' => [
                'nullable',
                'integer',
                Rule::exists('saloon_branches', 'id')->where(
                    fn ($query) => $query->where('saloon_id', $saloonId),
                ),
            ],
            'manager' => ['nullable', 'array'],
            'manager.firstname' => ['required_with:manager', 'string', 'max:120'],
            'manager.lastname' => ['required_with:manager', 'string', 'max:120'],
            'manager.email' => ['required_with:manager', 'string', 'email', 'max:120', 'unique:users,email'],
            'manager.phone' => ['required_with:manager', 'string', 'regex:/^\+?[0-9\s\-()]{7,20}$/', 'unique:users,phone'],
            'manager.password' => ['required_with:manager', 'string', 'confirmed', \App\Support\PasswordRules::defaults()],
            'manager.is_active' => ['sometimes', 'boolean'],
        ];
    }
}
