<?php

namespace App\Http\Resources\Api\V1\Branch;

use App\Models\SaloonBranch;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SaloonBranch */
class BranchResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'saloon_id' => $this->saloon_id,
            'branch_name' => $this->branch_name,
            'business_address_1' => $this->business_address_1,
            'business_address_2' => $this->business_address_2,
            'city' => $this->city,
            'state' => $this->state,
            'area_pincode' => $this->area_pincode,
            'country' => $this->country,
            'is_active' => (bool) $this->is_active,
            'catalog_items_count' => $this->whenCounted('catalogItems'),
            'users_count' => $this->whenCounted('users'),
            'saloon' => $this->whenLoaded('saloon', fn () => $this->saloon ? [
                'id' => $this->saloon->id,
                'name' => $this->saloon->name,
                'city' => $this->saloon->city,
                'phone' => $this->saloon->phone,
                'is_active' => (bool) $this->saloon->is_active,
            ] : null),
            'manager' => $this->whenLoaded('manager', fn () => $this->manager ? [
                'id' => $this->manager->id,
                'name' => $this->manager->name,
                'email' => $this->manager->email,
                'phone' => $this->manager->phone,
                'role_id' => $this->manager->role_id,
            ] : null),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
