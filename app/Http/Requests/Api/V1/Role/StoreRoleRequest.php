<?php

namespace App\Http\Requests\Api\V1\Role;

use App\Models\Role;
use App\Support\Concerns\AuthorizesPermission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRoleRequest extends FormRequest
{
    use AuthorizesPermission;

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => $this->filled('name') ? trim((string) $this->input('name')) : null,
            'code' => $this->filled('code') ? trim((string) $this->input('code')) : null,
        ]);
    }

    public function authorize(): bool
    {
        return $this->userCan('roles.create');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'code' => [
                'nullable',
                'string',
                'max:120',
                'regex:/^[a-z0-9._-]+$/',
                Rule::unique('roles', 'code'),
            ],
            'is_active' => ['required', 'boolean'],
            'scope' => ['sometimes', 'string', Rule::in([Role::SCOPE_SALON, Role::SCOPE_BRANCH])],
            'hierarchy_level' => ['sometimes', 'integer', 'min:1', 'max:99'],
            'saloon_id' => ['nullable', 'integer', 'exists:saloons,id'],
            'branch_id' => ['nullable', 'integer', 'exists:saloon_branches,id'],
        ];
    }
}
