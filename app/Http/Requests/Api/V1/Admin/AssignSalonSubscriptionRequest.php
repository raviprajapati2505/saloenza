<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Support\Concerns\AuthorizesPermission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignSalonSubscriptionRequest extends FormRequest
{
    use AuthorizesPermission;

    public function authorize(): bool
    {
        return $this->userCan('platform.upgrade_requests.update');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'subscription_plan_id' => ['required', 'integer', 'exists:subscription_plans,id'],
            'payment_type' => ['nullable', 'string', Rule::in([
                'cash', 'upi', 'card', 'bank_transfer', 'online', 'offline', 'other',
                'Monthly', 'Quarterly', 'Yearly', 'One-time',
            ])],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'transaction_id' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'start_trial' => ['sometimes', 'boolean'],
            'trial_days' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:365'],
        ];
    }
}
