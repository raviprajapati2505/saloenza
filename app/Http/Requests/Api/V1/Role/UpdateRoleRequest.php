<?php

namespace App\Http\Requests\Api\V1\Role;

use App\Support\Concerns\AuthorizesPermission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRoleRequest extends FormRequest
{
    use AuthorizesPermission;

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => $this->filled('name') ? trim((string) $this->input('name')) : null,
        ]);
    }

    public function authorize(): bool
    {
        return $this->userCan('roles.update');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var \App\Models\Role|null $role */
        $role = $this->route('role');

        return [
            'name' => [
                'required',
                'string',
                'max:120',
                Rule::unique('roles', 'name')
                    ->where(fn ($query) => $query->where('saloon_id', $this->input('saloon_id', $role?->saloon_id)))
                    ->ignore($role?->id),
            ],
            'is_active' => ['required', 'boolean'],
            'saloon_id' => ['nullable', 'integer', 'exists:saloons,id'],
            'branch_id' => ['nullable', 'integer', 'exists:saloon_branches,id'],
        ];
    }
}
