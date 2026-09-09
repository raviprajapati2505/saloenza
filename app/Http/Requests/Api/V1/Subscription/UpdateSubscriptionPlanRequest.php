<?php

namespace App\Http\Requests\Api\V1\Subscription;

use App\Models\SubscriptionPlan;
use App\Support\Concerns\AuthorizesPermission;
use App\Support\Subscription\SubscriptionModules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSubscriptionPlanRequest extends FormRequest
{
    use AuthorizesPermission;

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => $this->filled('name') ? trim((string) $this->input('name')) : null,
            'slug' => $this->filled('slug') ? strtolower(trim((string) $this->input('slug'))) : null,
        ]);
    }

    public function authorize(): bool
    {
        return $this->userCan('platform.subscription_plans.update');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var SubscriptionPlan|null $plan */
        $plan = $this->route('subscription_plan');

        return [
            'name' => ['required', 'string', 'max:120'],
            'slug' => [
                'required',
                'string',
                'max:80',
                'alpha_dash',
                Rule::unique('subscription_plans', 'slug')->ignore($plan?->id),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            'price' => ['required', 'numeric', 'min:0'],
            'billing_interval' => ['required', 'string', Rule::in(['monthly', 'quarterly', 'yearly', 'trial'])],
            'trial_days' => ['required', 'integer', 'min:0', 'max:365'],
            'max_branches' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'max_staff' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'modules' => ['required', 'array', 'min:1'],
            'modules.*' => ['string', Rule::in(SubscriptionModules::all())],
            'is_active' => ['required', 'boolean'],
            'is_public' => ['required', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:9999'],
        ];
    }
}
