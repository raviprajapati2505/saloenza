<?php

namespace App\Http\Requests\Api\V1\Appointment;

use App\Support\Appointment\AppointmentPayment;
use App\Support\Concerns\AuthorizesPermission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CaptureAppointmentPaymentRequest extends FormRequest
{
    use AuthorizesPermission;

    public function authorize(): bool
    {
        if ($this->user()?->grantsAllPermissions()) {
            return false;
        }

        return $this->userCan('appointments.update');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['required', 'string', Rule::in(AppointmentPayment::METHODS)],
        ];
    }
}
