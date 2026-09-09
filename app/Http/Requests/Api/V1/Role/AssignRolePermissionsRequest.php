<?php

namespace App\Http\Requests\Api\V1\Role;

use App\Support\Concerns\AuthorizesPermission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignRolePermissionsRequest extends FormRequest
{
    use AuthorizesPermission;

    public function authorize(): bool
    {
        return $this->userCan('assign_permissions.update');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'permission_ids' => ['required', 'array'],
            'permission_ids.*' => [
                'required',
                'uuid',
                Rule::exists('permissions', 'id'),
            ],
        ];
    }
}
