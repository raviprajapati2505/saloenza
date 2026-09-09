<?php

namespace App\Http\Requests\Api\V1\Tenant;

use Illuminate\Foundation\Http\FormRequest;

class UploadSalonLogoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'logo' => ['required', 'image', 'max:2048'],
        ];
    }
}
