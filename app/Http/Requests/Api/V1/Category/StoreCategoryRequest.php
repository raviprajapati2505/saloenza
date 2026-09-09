<?php

namespace App\Http\Requests\Api\V1\Category;

use App\Support\Concerns\AuthorizesPermission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCategoryRequest extends FormRequest
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
        return $this->userCan('categories.create');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:120',
                Rule::unique('categories', 'name')
                    ->where(fn ($query) => $query->whereNull('deleted_at')),
            ],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
