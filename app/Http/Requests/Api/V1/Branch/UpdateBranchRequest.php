<?php

namespace App\Http\Requests\Api\V1\Branch;

use App\Models\SaloonBranch;
use App\Support\Concerns\AuthorizesPermission;
use Illuminate\Foundation\Http\FormRequest;

class UpdateBranchRequest extends FormRequest
{
    use AuthorizesPermission;

    protected function prepareForValidation(): void
    {
        $payload = [
            'branch_name' => $this->filled('branch_name') ? trim((string) $this->input('branch_name')) : null,
            'business_address_1' => $this->filled('business_address_1') ? trim((string) $this->input('business_address_1')) : null,
            'business_address_2' => $this->exists('business_address_2')
                ? trim((string) $this->input('business_address_2'))
                : null,
            'city' => $this->filled('city') ? trim((string) $this->input('city')) : null,
            'state' => $this->filled('state') ? trim((string) $this->input('state')) : null,
            'area_pincode' => $this->filled('area_pincode') ? trim((string) $this->input('area_pincode')) : null,
            'country' => $this->filled('country') ? trim((string) $this->input('country')) : null,
        ];

        $user = $this->user();
        $user?->loadMissing('role');

        /** @var SaloonBranch|null $branch */
        $branch = $this->route('branch');
        if ($user?->isBranchScopedActor() && $branch instanceof SaloonBranch) {
            $payload['is_active'] = (bool) $branch->is_active;
        }

        $this->merge($payload);
    }

    public function authorize(): bool
    {
        if (! $this->userCan('branches.update')) {
            return false;
        }

        /** @var SaloonBranch|null $branch */
        $branch = $this->route('branch');
        $user = $this->user();

        if ($branch === null || $user === null || (int) $branch->saloon_id !== (int) $user->saloon_id) {
            return false;
        }

        $user->loadMissing('role');
        if ($user->isBranchScopedActor() && $user->branch_id !== null
            && (int) $branch->id !== (int) $user->branch_id) {
            return false;
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'branch_name' => ['required', 'string', 'max:120'],
            'business_address_1' => ['required', 'string', 'max:255'],
            'business_address_2' => ['nullable', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:120'],
            'state' => ['required', 'string', 'max:120'],
            'area_pincode' => ['required', 'string', 'max:20'],
            'country' => ['nullable', 'string', 'max:120'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
