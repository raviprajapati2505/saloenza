<?php

namespace App\Http\Requests\Api\V1\Onboarding;

use Illuminate\Foundation\Http\FormRequest;

class BranchStepRequest extends FormRequest
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
            'branchName' => ['required', 'string', 'max:120'],
            'address' => ['required', 'string', 'max:1000'],
            'city' => ['required', 'string', 'max:120'],
            'state' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'regex:/^\+?[0-9\s\-()]{7,20}$/'],
            'whatsapp' => ['nullable', 'string', 'regex:/^\+?[0-9\s\-()]{7,20}$/'],
            'gstNumber' => ['nullable', 'string', 'regex:/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][0-9A-Z]Z[0-9A-Z]$/'],
            'hours' => ['required', 'array'],
        ];
    }
}
