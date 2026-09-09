<?php

namespace App\Http\Resources\Api\V1\Shared;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class UserResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, int|string|bool|null>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'photo' => $this->photo_url,
            'is_system_admin' => (bool) $this->is_system_admin,
            'role_id' => $this->role_id,
            'saloon_id' => $this->saloon_id,
            'branch_id' => $this->branch_id,
        ];
    }
}
