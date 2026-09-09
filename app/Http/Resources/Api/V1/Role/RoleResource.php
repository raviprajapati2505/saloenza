<?php

namespace App\Http\Resources\Api\V1\Role;

use App\Http\Resources\Api\V1\Permission\PermissionResource;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Role */
class RoleResource extends JsonResource
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
            'code' => $this->code,
            'is_active' => (bool) $this->is_active,
            'is_system' => (bool) $this->is_system,
            'scope' => $this->scope,
            'hierarchy_level' => $this->hierarchy_level,
            'saloon_id' => $this->saloon_id,
            'branch_id' => $this->branch_id,
            'permissions' => $this->whenLoaded(
                'permissions',
                fn () => PermissionResource::collection($this->permissions)->resolve(),
            ),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
