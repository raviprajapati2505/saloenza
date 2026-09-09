<?php

namespace App\Http\Requests\Api\V1\Catalog;

use App\Support\Concerns\AuthorizesPermission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSalonCatalogRequest extends FormRequest
{
    use AuthorizesPermission;

    public function authorize(): bool
    {
        return $this->userCanAny(['services.update', 'products.update']);
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
            'service_id' => ['required', 'integer', 'exists:services,id'],
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'price' => ['required', 'numeric', 'min:0'],
            'duration_minutes' => ['required', 'integer', 'min:1', 'max:1440'],
            'is_active' => ['required', 'boolean'],
            'branch_id' => $branchIdRule,
        ];
    }
}
