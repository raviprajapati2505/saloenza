<?php

namespace App\Http\Requests\Api\V1\Customer;

use App\Support\Concerns\AuthorizesPermission;
use App\Support\Phone\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCustomerRequest extends FormRequest
{
    use AuthorizesPermission;

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => $this->filled('name') ? trim((string) $this->input('name')) : null,
            'email' => $this->filled('email') ? strtolower(trim((string) $this->input('email'))) : null,
            'phone' => $this->filled('phone') ? PhoneNumber::normalize($this->input('phone')) : null,
            'whatsapp' => $this->filled('whatsapp') ? PhoneNumber::normalize($this->input('whatsapp')) : null,
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
            'saloon_id' => [
                Rule::requiredIf(fn () => (bool) $this->user()?->is_system_admin),
                'nullable',
                'integer',
                'exists:saloons,id',
            ],
            'name' => ['required', 'string', 'max:120'],
            'email' => ['nullable', 'string', 'email', 'max:120'],
            'phone' => ['nullable', 'string', 'regex:'.PhoneNumber::E164_REGEX],
            'whatsapp' => ['nullable', 'string', 'regex:'.PhoneNumber::E164_REGEX],
            'notes' => ['nullable', 'string', 'max:2000'],
            'birthday' => ['nullable', 'date'],
            'anniversary' => ['nullable', 'date'],
            'is_active' => ['required', 'boolean'],
            'tag_ids' => ['sometimes', 'array'],
            'tag_ids.*' => ['integer', 'exists:customer_tags,id'],
        ];
    }
}
