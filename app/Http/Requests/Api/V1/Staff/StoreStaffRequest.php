<?php

namespace App\Http\Requests\Api\V1\Staff;

use App\Support\Concerns\AuthorizesPermission;
use App\Support\PasswordRules;
use App\Support\Phone\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStaffRequest extends FormRequest
{
    use AuthorizesPermission;

    protected function prepareForValidation(): void
    {
        $this->merge([
            'firstname' => $this->filled('firstname') ? trim((string) $this->input('firstname')) : null,
            'lastname' => $this->filled('lastname') ? trim((string) $this->input('lastname')) : null,
            'email' => $this->filled('email') ? strtolower(trim((string) $this->input('email'))) : null,
            'phone' => $this->filled('phone') ? PhoneNumber::normalize($this->input('phone')) : null,
            'whatsapp' => $this->filled('whatsapp') ? PhoneNumber::normalize($this->input('whatsapp')) : null,
            'notes' => $this->filled('notes') ? trim((string) $this->input('notes')) : null,
        ]);
    }

    public function authorize(): bool
    {
        return $this->userCan('staff.create') || $this->userIsSystemAdmin();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var \App\Models\User $user */
        $user = $this->user();
        $saloonId = $user->is_system_admin
            ? ($this->filled('saloon_id') ? (int) $this->input('saloon_id') : null)
            : (int) $user->saloon_id;

        $roleExistsRule = Rule::exists('roles', 'id')->where('is_active', true);

        if ($saloonId !== null) {
            $roleExistsRule = Rule::exists('roles', 'id')->where(function ($query) use ($saloonId): void {
                $query->where(function ($builder) use ($saloonId): void {
                    $builder->whereNull('saloon_id')
                        ->orWhere('saloon_id', $saloonId);
                })->where('is_active', true);
            });
        }

        $branchExistsRule = ['nullable', 'integer', 'exists:saloon_branches,id'];

        if ($saloonId !== null) {
            $branchExistsRule = [
                'nullable',
                'integer',
                Rule::exists('saloon_branches', 'id')->where(
                    fn ($query) => $query->where('saloon_id', $saloonId),
                ),
            ];
        }

        return [
            'firstname' => ['required', 'string', 'max:120'],
            'lastname' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:120', Rule::unique('users', 'email')],
            'phone' => ['required', 'string', 'regex:'.PhoneNumber::E164_REGEX, Rule::unique('users', 'phone')],
            'whatsapp' => ['nullable', 'string', 'regex:'.PhoneNumber::E164_REGEX],
            'password' => ['required', 'confirmed', PasswordRules::defaults()],
            'is_active' => ['required', 'boolean'],
            'role_id' => ['required', 'integer', $roleExistsRule],
            'saloon_id' => [
                Rule::requiredIf($user->is_system_admin),
                'nullable',
                'integer',
                'exists:saloons,id',
            ],
            'branch_id' => $branchExistsRule,
            'commission_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'per_month_salary' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'joined_at' => ['nullable', 'date'],
            'weekly_schedule' => ['nullable', 'array'],
        ];
    }
}
