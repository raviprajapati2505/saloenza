<?php

namespace App\Http\Requests\Api\V1\Onboarding;

use Illuminate\Foundation\Http\FormRequest;

class AccountStepRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'plan' => ['nullable', 'string', 'max:60'],
            'plan_slug' => ['nullable', 'string', 'max:80'],
            'subscription_plan_id' => ['nullable', 'integer', 'exists:subscription_plans,id'],
        ];
    }
}
