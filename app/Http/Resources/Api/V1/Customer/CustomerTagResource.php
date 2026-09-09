<?php

namespace App\Http\Resources\Api\V1\Customer;

use App\Models\CustomerTag;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CustomerTag */
class CustomerTagResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'saloon_id' => (int) $this->saloon_id,
            'name' => $this->name,
            'color' => $this->color,
            'customers_count' => $this->when(
                $this->customers_count !== null,
                fn () => (int) $this->customers_count,
            ),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
