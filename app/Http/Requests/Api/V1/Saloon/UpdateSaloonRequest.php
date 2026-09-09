<?php

namespace App\Http\Requests\Api\V1\Saloon;

use App\Models\Saloon;
use App\Support\Concerns\AuthorizesPermission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSaloonRequest extends FormRequest
{
    use AuthorizesPermission;

    public function authorize(): bool
    {
        return $this->userCan('platform.salon_onboarding.update');
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('business_name')) {
            $this->merge([
                'business_name' => trim((string) $this->input('business_name')),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Saloon|null $saloon */
        $saloon = $this->route('saloon');

        return [
            'business_name' => ['required', 'string', 'max:120'],
            'payment_type' => ['required', 'string', Rule::in([
                'online', 'cash', 'card', 'upi', 'bank_transfer', 'other',
                'monthly', 'quarterly', 'yearly', 'one-time',
                'Monthly', 'Quarterly', 'Yearly', 'One-time',
            ])],
            'payment_amount' => ['required', 'numeric', 'min:0'],
            'transaction_id' => ['nullable', 'string', 'max:120'],
            'is_active' => ['required', 'boolean'],
            'referral_code' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('saloons', 'referral_code')->ignore($saloon?->id),
            ],
        ];
    }
}
