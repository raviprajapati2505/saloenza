<?php

namespace App\Http\Requests\Api\V1\Catalog;

use App\Support\Concerns\AuthorizesPermission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSalonCatalogRequest extends FormRequest
{
    use AuthorizesPermission;

    public function authorize(): bool
    {
        return $this->userCanAny(['services.create', 'products.create']);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $branchIdRule = ['nullable', 'integer', 'exists:saloon_branches,id'];

        if ($this->user()?->saloon_id !== null) {
            $branchIdRule = [
                'nullable',
                'integer',
                Rule::exists('saloon_branches', 'id')->where(
                    fn ($query) => $query->where('saloon_id', $this->user()?->saloon_id),
                ),
            ];
        }

        return [
            'saloon_id' => [
                Rule::requiredIf(fn () => (bool) $this->user()?->is_system_admin),
                'nullable',
                'integer',
                'exists:saloons,id',
            ],
            'service_id' => ['required', 'integer', 'exists:services,id'],
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'price' => ['required', 'numeric', 'min:0'],
            'duration_minutes' => ['required', 'integer', 'min:1', 'max:1440'],
            'is_active' => ['required', 'boolean'],
            'branch_id' => $branchIdRule,
        ];
    }
}
