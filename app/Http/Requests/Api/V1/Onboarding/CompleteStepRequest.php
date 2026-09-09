<?php

namespace App\Http\Requests\Api\V1\Onboarding;

use Illuminate\Foundation\Http\FormRequest;

class CompleteStepRequest extends FormRequest
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
        return [];
    }
}
