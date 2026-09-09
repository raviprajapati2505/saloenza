<?php

namespace App\Http\Requests\Api\V1\Subscription;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmSubscriptionCheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->saloon_id !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'upgrade_order_id' => ['required', 'integer', 'exists:subscription_upgrade_orders,id'],
            'razorpay_payment_id' => ['required_without:stripe_session_id', 'nullable', 'string', 'max:120'],
            'razorpay_order_id' => ['required_with:razorpay_payment_id', 'nullable', 'string', 'max:120'],
            'razorpay_signature' => ['required_with:razorpay_payment_id', 'nullable', 'string', 'max:255'],
            'stripe_session_id' => ['required_without:razorpay_payment_id', 'nullable', 'string', 'max:255'],
        ];
    }
}
