<?php

namespace App\Http\Resources\Api\V1\Package;

use App\Models\Package;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Package */
class PackageResource extends JsonResource
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
            'name' => $this->name,
            'description' => $this->description,
            'price' => $this->price,
            'valid_days' => $this->valid_days,
            'is_active' => (bool) $this->is_active,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'branch' => $this->whenLoaded('branch', fn () => $this->branch ? [
                'id' => $this->branch->id,
                'branch_name' => $this->branch->branch_name,
            ] : null),
            'creator' => $this->whenLoaded('creator', fn () => $this->creator ? [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
            ] : null),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'id' => $item->id,
                'service_id' => $item->service_id,
                'quantity_total' => $item->quantity_total,
                'unit_value' => $item->unit_value,
                'service' => $item->relationLoaded('service') && $item->service ? [
                    'id' => $item->service->id,
                    'name' => $item->service->name,
                    'default_price' => $item->service->default_price,
                ] : null,
            ])->values()->all()),
            'items_count' => $this->when(isset($this->items_count), $this->items_count),
            'sold_count' => $this->when(isset($this->customer_packages_count), $this->customer_packages_count),
        ];
    }
}
