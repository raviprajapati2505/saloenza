<?php

namespace App\Http\Resources\Api\V1\Permission;

use App\Models\Permission;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Permission */
class PermissionResource extends JsonResource
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
            'module' => $this->module,
            'scope' => $this->scope,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
