<?php

namespace App\Http\Requests\Api\V1\Profile;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
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
        $userId = $this->user()?->id;

        return [
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'email' => [
                'sometimes',
                'required',
                'string',
                'email',
                'max:120',
                Rule::unique('users', 'email')->ignore($userId),
            ],
            'phone' => [
                'sometimes',
                'nullable',
                'string',
                'regex:/^\+?[0-9\s\-()]{7,20}$/',
                Rule::unique('users', 'phone')->ignore($userId),
            ],
            'photo' => ['sometimes', 'nullable', 'image', 'max:2048'],
        ];
    }
}
