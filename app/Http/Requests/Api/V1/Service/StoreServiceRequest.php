<?php

namespace App\Http\Requests\Api\V1\Service;

use App\Support\Concerns\AuthorizesPermission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreServiceRequest extends FormRequest
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
        return $this->userCanAsPlatform('services.create');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120', Rule::unique('services', 'name')],
            'category_id' => [
                'required',
                'integer',
                Rule::exists('categories', 'id')->where(fn ($query) => $query->whereNull('deleted_at')),
            ],
            'default_price' => ['required', 'numeric', 'min:0'],
            'duration_minutes' => ['required', 'integer', 'min:1', 'max:1440'],
            'gender' => ['nullable', 'string', Rule::in(['Male', 'Female', 'Unisex'])],
            'is_active' => ['required', 'boolean'],
            'products' => ['sometimes', 'array'],
            'products.*.product_id' => ['required', 'integer', 'distinct', 'exists:products,id'],
            'products.*.default_price' => ['required', 'numeric', 'min:0'],
        ];
    }
}
