<?php

namespace App\Http\Requests\Api\V1\Product;

use App\Support\Concerns\AuthorizesPermission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
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
        return $this->userCanAsPlatform('products.create');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120', Rule::unique('products', 'name')],
            'category_id' => [
                'required',
                'integer',
                Rule::exists('categories', 'id')->where(fn ($query) => $query->whereNull('deleted_at')),
            ],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
