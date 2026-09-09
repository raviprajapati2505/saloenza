<?php

namespace App\Http\Requests\Api\V1\Appointment;

use App\Support\Concerns\AuthorizesPermission;
use Illuminate\Foundation\Http\FormRequest;

class RefundAppointmentPaymentRequest extends FormRequest
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
            'amount' => ['nullable', 'numeric', 'min:0.01'],
        ];
    }
}
