<?php

namespace App\Http\Requests\Api\V1\Pos;

use App\Support\Appointment\AppointmentPayment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PosCheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'branch_id' => ['required', 'integer', 'exists:saloon_branches,id'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:30'],
            'discount' => ['sometimes', 'numeric', 'min:0'],
            'status' => ['sometimes', 'string', Rule::in(['scheduled', 'confirmed', 'in-progress', 'completed', 'cancelled', 'no-show'])],
            'notes' => ['nullable', 'string', 'max:2000'],
            'collect_payment' => ['sometimes', 'boolean'],
            'payment_method' => ['required_if:collect_payment,true', 'nullable', 'string', Rule::in(AppointmentPayment::METHODS)],
            'amount_paid' => ['nullable', 'numeric', 'min:0.01'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.kind' => ['sometimes', 'string', Rule::in(['service', 'retail'])],
            'items.*.service_id' => ['nullable', 'integer', 'exists:services,id'],
            'items.*.product_id' => ['nullable', 'integer', 'exists:products,id'],
            'items.*.staff_id' => ['nullable', 'integer', 'exists:users,id'],
            'items.*.quantity' => ['sometimes', 'integer', 'min:1', 'max:99'],
            'items.*.price' => ['nullable', 'numeric', 'min:0'],
            'items.*.duration_minutes' => ['nullable', 'integer', 'min:1', 'max:480'],
            'items.*.is_staff_locked' => ['sometimes', 'boolean'],
        ];
    }
}
