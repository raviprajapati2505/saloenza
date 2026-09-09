<?php

namespace App\Http\Requests\Api\V1\Onboarding;

use Illuminate\Foundation\Http\FormRequest;

class ServicesStepRequest extends FormRequest
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
            'skipped' => ['required', 'boolean'],
            'services' => ['required_if:skipped,false', 'array', 'max:50'],
            'services.*.service_id' => ['nullable', 'integer', 'exists:services,id'],
            'services.*.product_id' => ['nullable', 'integer', 'exists:products,id'],
            'services.*.category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'services.*.name' => ['required_with:services', 'string', 'max:120'],
            'services.*.category' => ['nullable', 'string', 'max:120'],
            'services.*.product' => ['nullable', 'string', 'max:120'],
            'services.*.duration' => ['required_with:services', 'integer', 'min:1', 'max:480'],
            'services.*.price' => ['required_with:services', 'numeric', 'min:0'],
        ];
    }
}
