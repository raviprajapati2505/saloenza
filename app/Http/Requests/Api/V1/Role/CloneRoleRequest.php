<?php

namespace App\Http\Requests\Api\V1\Role;

use App\Support\Concerns\AuthorizesPermission;
use Illuminate\Foundation\Http\FormRequest;

class CloneRoleRequest extends FormRequest
{
    use AuthorizesPermission;

    protected function prepareForValidation(): void
    {
        if ($this->filled('name')) {
            $this->merge([
                'name' => trim((string) $this->input('name')),
            ]);
        }
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
            'name' => ['sometimes', 'nullable', 'string', 'max:120'],
            'saloon_id' => ['nullable', 'integer', 'exists:saloons,id'],
        ];
    }
}
