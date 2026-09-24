<?php

namespace App\Http\Resources\Api\V1\Staff;

use App\Http\Resources\Api\V1\Role\RoleResource;
use App\Http\Resources\Api\V1\Saloon\SaloonResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class StaffResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'firstname' => $this->firstname,
            'lastname' => $this->lastname,
            'email' => $this->email,
            'phone' => $this->phone,
            'whatsapp' => $this->whatsapp,
            'photo' => $this->photo,
            'photo_url' => $this->photo_url,
            'is_active' => (bool) $this->is_active,
            'commission_rate' => $this->commission_rate !== null ? (float) $this->commission_rate : null,
            'per_month_salary' => $this->per_month_salary !== null ? (float) $this->per_month_salary : null,
            'notes' => $this->notes,
            'joined_at' => $this->joined_at?->toDateString(),
            'weekly_schedule' => $this->weekly_schedule,
            'role_id' => $this->role_id,
            'saloon_id' => $this->saloon_id,
            'branch_id' => $this->branch_id,
            'onboarding_completed_at' => $this->onboarding_completed_at?->toISOString(),
            'role' => $this->whenLoaded(
                'role',
                fn () => (new RoleResource($this->role))->resolve(),
            ),
            'saloon' => $this->whenLoaded(
                'saloon',
                fn () => (new SaloonResource($this->saloon))->resolve(),
            ),
            'branch' => $this->whenLoaded(
                'branch',
                fn () => [
                    'id' => $this->branch?->id,
                    'branch_name' => $this->branch?->branch_name,
                    'saloon_id' => $this->branch?->saloon_id,
                    'is_active' => (bool) $this->branch?->is_active,
                ],
            ),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
