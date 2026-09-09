<?php

namespace App\Http\Requests\Api\V1\Customer;

use App\Support\Concerns\AuthorizesPermission;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCustomerRequest extends FormRequest
{
    use AuthorizesPermission;

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => $this->filled('name') ? trim((string) $this->input('name')) : null,
            'email' => $this->filled('email') ? strtolower(trim((string) $this->input('email'))) : null,
            'phone' => $this->filled('phone') ? trim((string) $this->input('phone')) : null,
        ]);
    }

    public function authorize(): bool
    {
        return $this->userCan('customers.manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['nullable', 'string', 'email', 'max:120'],
            'phone' => ['nullable', 'string', 'max:30'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'birthday' => ['nullable', 'date'],
            'anniversary' => ['nullable', 'date'],
            'is_active' => ['required', 'boolean'],
            'tag_ids' => ['sometimes', 'array'],
            'tag_ids.*' => ['integer', 'exists:customer_tags,id'],
        ];
    }
}
