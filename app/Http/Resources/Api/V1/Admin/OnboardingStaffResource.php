<?php

namespace App\Http\Resources\Api\V1\Admin;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class OnboardingStaffResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, int|bool|string|null>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'firstname' => $this->firstname,
            'lastname' => $this->lastname,
            'email' => $this->email,
            'phone' => $this->phone,
            'saloon_id' => $this->saloon_id,
            'branch_id' => $this->branch_id,
            'role_id' => $this->role_id,
            'is_active' => (bool) $this->is_active,
        ];
    }
}
