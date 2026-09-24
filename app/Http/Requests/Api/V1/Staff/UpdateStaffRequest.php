<?php

namespace App\Http\Requests\Api\V1\Staff;

use App\Models\User;
use App\Support\Concerns\AuthorizesPermission;
use App\Support\PasswordRules;
use App\Support\Phone\PhoneNumber;
use App\Support\Staff\StaffAccess;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStaffRequest extends FormRequest
{
    use AuthorizesPermission;

    protected function prepareForValidation(): void
    {
        $payload = [
            'firstname' => $this->filled('firstname') ? trim((string) $this->input('firstname')) : null,
            'lastname' => $this->filled('lastname') ? trim((string) $this->input('lastname')) : null,
            'email' => $this->filled('email') ? strtolower(trim((string) $this->input('email'))) : null,
            'phone' => $this->filled('phone') ? PhoneNumber::normalize($this->input('phone')) : null,
            'whatsapp' => $this->exists('whatsapp')
                ? ($this->filled('whatsapp') ? PhoneNumber::normalize($this->input('whatsapp')) : null)
                : null,
        ];

        if ($this->exists('notes')) {
            $payload['notes'] = $this->filled('notes') ? trim((string) $this->input('notes')) : null;
        }

        if (! $this->exists('whatsapp')) {
            unset($payload['whatsapp']);
        }

        $this->merge($payload);
    }

    public function authorize(): bool
    {
        return $this->userCan('staff.update') || $this->userIsSystemAdmin();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var User $staff */
        $staff = $this->route('staff');
        /** @var User $user */
        $user = $this->user();

        StaffAccess::ensureWritable($user, $staff, 'staff.update');

        $saloonId = $user->is_system_admin
            ? (int) ($this->input('saloon_id', $staff->saloon_id))
            : (int) $user->saloon_id;

        return [
            'firstname' => ['required', 'string', 'max:120'],
            'lastname' => ['required', 'string', 'max:120'],
            'email' => [
                'required',
                'string',
                'email',
                'max:120',
                Rule::unique('users', 'email')->ignore($staff->id),
            ],
            'phone' => [
                'required',
                'string',
                'regex:'.PhoneNumber::E164_REGEX,
                Rule::unique('users', 'phone')->ignore($staff->id),
            ],
            'whatsapp' => [
                'nullable',
                'string',
                'regex:'.PhoneNumber::E164_REGEX,
            ],
            'password' => ['nullable', 'confirmed', PasswordRules::defaults()],
            'is_active' => ['required', 'boolean'],
            'role_id' => [
                'required',
                'integer',
                Rule::exists('roles', 'id')->where(function ($query) use ($saloonId): void {
                    $query->where(function ($builder) use ($saloonId): void {
                        $builder->whereNull('saloon_id')
                            ->orWhere('saloon_id', $saloonId);
                    })->where('is_active', true);
                }),
            ],
            'saloon_id' => [
                Rule::requiredIf($user->is_system_admin),
                'nullable',
                'integer',
                'exists:saloons,id',
            ],
            'branch_id' => [
                'nullable',
                'integer',
                Rule::exists('saloon_branches', 'id')->where(
                    fn ($query) => $query->where('saloon_id', $saloonId),
                ),
            ],
            'commission_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'per_month_salary' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'joined_at' => ['sometimes', 'nullable', 'date'],
            'weekly_schedule' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
