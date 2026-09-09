<?php

namespace App\Http\Resources\Api\V1\Admin;

use App\Models\SalonServiceProduct;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SalonServiceProduct */
class SalonServiceProductResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, int|float|bool|string|null>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'saloon_id' => $this->saloon_id,
            'service_id' => $this->service_id,
            'product_id' => $this->product_id,
            'service_name' => $this->relationLoaded('service') ? $this->service?->name : null,
            'product_name' => $this->relationLoaded('product') ? $this->product?->name : null,
            'price' => $this->price,
            'duration_minutes' => $this->duration_minutes,
            'is_active' => (bool) $this->is_active,
        ];
    }
}
