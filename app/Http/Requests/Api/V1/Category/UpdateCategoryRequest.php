<?php

namespace App\Http\Requests\Api\V1\Category;

use App\Models\Category;
use App\Support\Concerns\AuthorizesPermission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCategoryRequest extends FormRequest
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
        return $this->userCan('categories.update');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Category|null $category */
        $category = $this->route('category');

        return [
            'name' => [
                'required',
                'string',
                'max:120',
                Rule::unique('categories', 'name')
                    ->where(fn ($query) => $query->whereNull('deleted_at'))
                    ->ignore($category?->id),
            ],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
