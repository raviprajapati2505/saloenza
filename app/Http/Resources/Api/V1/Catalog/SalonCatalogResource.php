<?php

namespace App\Http\Resources\Api\V1\Catalog;

use App\Models\SalonServiceProduct;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SalonServiceProduct */
class SalonCatalogResource extends JsonResource
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
            'price' => $this->price,
            'duration_minutes' => $this->duration_minutes,
            'is_active' => (bool) $this->is_active,
            'branch' => $this->whenLoaded('branch', fn () => $this->branch ? [
                'id' => $this->branch->id,
                'branch_name' => $this->branch->branch_name,
            ] : null),
            'service' => $this->whenLoaded('service', fn () => [
                'id' => $this->service?->id,
                'name' => $this->service?->name,
                'category_id' => $this->service?->category_id,
                'category' => $this->service?->relationLoaded('category') && $this->service?->category
                    ? [
                        'id' => $this->service->category->id,
                        'name' => $this->service->category->name,
                    ]
                    : null,
            ]),
            'product' => $this->whenLoaded('product', fn () => [
                'id' => $this->product?->id,
                'name' => $this->product?->name,
                'category_id' => $this->product?->category_id,
                'category' => $this->product?->relationLoaded('category') && $this->product?->category
                    ? [
                        'id' => $this->product->category->id,
                        'name' => $this->product->category->name,
                    ]
                    : null,
            ]),
        ];
    }
}
