<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Support\Concerns\AuthorizesPermission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ApproveSubscriptionUpgradeRequest extends FormRequest
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
            'payment_type' => ['required', 'string', Rule::in([
                'cash', 'upi', 'card', 'bank_transfer', 'online', 'other',
                'Monthly', 'Quarterly', 'Yearly', 'One-time',
            ])],
            'amount' => ['required', 'numeric', 'min:0'],
            'transaction_id' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
