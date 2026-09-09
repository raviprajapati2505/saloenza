<?php

namespace App\Http\Requests\Api\V1\Subscription;

use Illuminate\Foundation\Http\FormRequest;

class CheckoutSubscriptionRequest extends FormRequest
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
            'subscription_plan_id' => ['required', 'integer', 'exists:subscription_plans,id'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
