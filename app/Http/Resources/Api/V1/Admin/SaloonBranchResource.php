<?php

namespace App\Http\Resources\Api\V1\Admin;

use App\Models\SaloonBranch;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SaloonBranch */
class SaloonBranchResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, int|bool|string|null>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'branch_name' => $this->branch_name,
            'business_address_1' => $this->business_address_1,
            'business_address_2' => $this->business_address_2,
            'saloon_id' => $this->saloon_id,
            'city' => $this->city,
            'state' => $this->state,
            'area_pincode' => $this->area_pincode,
            'country' => $this->country,
            'is_active' => (bool) $this->is_active,
        ];
    }
}
