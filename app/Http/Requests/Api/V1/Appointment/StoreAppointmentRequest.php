<?php

namespace App\Http\Requests\Api\V1\Appointment;

use App\Models\Appointment;
use App\Support\Appointment\AppointmentPayment;
use App\Support\Concerns\AuthorizesPermission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAppointmentRequest extends FormRequest
{
    use AuthorizesPermission;

    public function authorize(): bool
    {
        if ($this->user()?->grantsAllPermissions()) {
            return false;
        }

        return $this->userCan('appointments.create');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $saloonId = $this->user()?->is_system_admin
            ? ($this->filled('saloon_id') ? (int) $this->input('saloon_id') : null)
            : $this->user()?->saloon_id;

        $branchRule = ['nullable', 'integer', 'exists:saloon_branches,id'];

        if ($saloonId !== null) {
            $branchRule = [
                'nullable',
                'integer',
                Rule::exists('saloon_branches', 'id')->where(
                    fn ($query) => $query->where('saloon_id', $saloonId),
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
            'branch_id' => $branchRule,
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'customer_name' => ['required_without:customer_id', 'nullable', 'string', 'max:120'],
            'customer_phone' => ['nullable', 'string', 'max:30'],
            'type' => ['required', Rule::in(Appointment::TYPES)],
            'starts_at' => ['required', 'date'],
            'status' => ['nullable', 'string', 'max:30'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'collect_payment' => ['sometimes', 'boolean'],
            'payment_method' => ['nullable', 'string', Rule::in(AppointmentPayment::METHODS)],
            'amount_paid' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'services' => ['required_without:products', 'array'],
            'services.*.service_id' => ['required', 'integer', 'exists:services,id'],
            'services.*.product_id' => ['nullable', 'integer', 'exists:products,id'],
            'services.*.staff_id' => ['nullable', 'integer', 'exists:users,id'],
            'services.*.is_staff_locked' => ['nullable', 'boolean'],
            'services.*.price' => ['nullable', 'numeric', 'min:0'],
            'services.*.duration_minutes' => ['nullable', 'integer', 'min:1', 'max:1440'],
            'services.*.starts_at' => ['nullable', 'date'],
            'services.*.ends_at' => ['nullable', 'date', 'after:services.*.starts_at'],
            // Retail products sold with the visit, or on their own for a counter sale.
            'products' => ['required_without:services', 'array'],
            'products.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'products.*.quantity' => ['nullable', 'integer', 'min:1', 'max:999'],
            'products.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'products.*.staff_id' => ['nullable', 'integer', 'exists:users,id'],
            // Legacy single-service fields (accepted and mapped when services omitted by older clients)
            'staff_id' => ['nullable', 'integer', 'exists:users,id'],
            'service_id' => ['nullable', 'integer', 'exists:services,id'],
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('services') || ! $this->filled('service_id')) {
            return;
        }

        $this->merge([
            'services' => [[
                'service_id' => $this->input('service_id'),
                'product_id' => $this->input('product_id'),
                'staff_id' => $this->input('staff_id'),
                'price' => $this->input('price'),
            ]],
            'type' => $this->input('type', Appointment::TYPE_APPOINTMENT),
        ]);
    }
}
