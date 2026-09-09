<?php

namespace App\Http\Requests\Api\V1\Customer;

use App\Support\Concerns\AuthorizesPermission;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCustomerTagRequest extends FormRequest
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
        return $this->userCan('customers.manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:60'],
            'color' => ['nullable', 'string', 'max:20'],
        ];
    }
}
