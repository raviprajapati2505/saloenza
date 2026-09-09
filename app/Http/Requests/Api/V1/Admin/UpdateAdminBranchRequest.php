<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Support\Concerns\AuthorizesPermission;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAdminBranchRequest extends FormRequest
{
    use AuthorizesPermission;

    protected function prepareForValidation(): void
    {
        $this->merge([
            'branch_name' => $this->filled('branch_name') ? trim((string) $this->input('branch_name')) : null,
            'business_address_1' => $this->filled('business_address_1') ? trim((string) $this->input('business_address_1')) : null,
            'business_address_2' => $this->exists('business_address_2')
                ? trim((string) $this->input('business_address_2'))
                : null,
            'city' => $this->filled('city') ? trim((string) $this->input('city')) : null,
            'state' => $this->filled('state') ? trim((string) $this->input('state')) : null,
            'area_pincode' => $this->filled('area_pincode') ? trim((string) $this->input('area_pincode')) : null,
            'country' => $this->filled('country') ? trim((string) $this->input('country')) : null,
        ]);
    }

    public function authorize(): bool
    {
        return $this->userCan('platform.branches.update') || $this->userIsSystemAdmin();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'branch_name' => ['required', 'string', 'max:120'],
            'business_address_1' => ['required', 'string', 'max:255'],
            'business_address_2' => ['nullable', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:120'],
            'state' => ['required', 'string', 'max:120'],
            'area_pincode' => ['required', 'string', 'max:20'],
            'country' => ['nullable', 'string', 'max:120'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
