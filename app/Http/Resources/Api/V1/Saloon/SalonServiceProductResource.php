<?php

namespace App\Http\Resources\Api\V1\Saloon;

use App\Models\SalonServiceProduct;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SalonServiceProduct */
class SalonServiceProductResource extends JsonResource
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
            'branch_id' => $this->branch_id,
            'service_id' => $this->service_id,
            'product_id' => $this->product_id,
            'price' => (float) $this->price,
            'duration_minutes' => $this->duration_minutes,
            'is_active' => (bool) $this->is_active,
            'branch' => $this->whenLoaded('branch', fn () => $this->branch ? [
                'id' => $this->branch->id,
                'branch_name' => $this->branch->branch_name,
            ] : null),
            'service' => $this->whenLoaded('service', fn () => [
                'id' => $this->service->id,
                'name' => $this->service->name,
            ]),
            'product' => $this->whenLoaded('product', fn () => $this->product ? [
                'id' => $this->product->id,
                'name' => $this->product->name,
            ] : null),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
