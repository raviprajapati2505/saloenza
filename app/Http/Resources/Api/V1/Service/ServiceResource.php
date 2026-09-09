<?php

namespace App\Http\Resources\Api\V1\Service;

use App\Http\Resources\Api\V1\Category\CategoryResource;
use App\Http\Resources\Api\V1\Product\ProductResource;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Service */
class ServiceResource extends JsonResource
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
            'category_id' => $this->category_id,
            'category' => $this->whenLoaded('category', fn () => (new CategoryResource($this->category))->resolve()),
            'default_price' => (float) $this->default_price,
            'duration_minutes' => $this->duration_minutes,
            'gender' => $this->gender,
            'is_active' => (bool) $this->is_active,
            'products' => $this->whenLoaded('serviceProducts', function () {
                return $this->serviceProducts->map(fn ($serviceProduct) => [
                    'id' => $serviceProduct->id,
                    'product_id' => $serviceProduct->product_id,
                    'default_price' => (float) $serviceProduct->default_price,
                    'product' => $serviceProduct->relationLoaded('product')
                        ? (new ProductResource($serviceProduct->product))->resolve()
                        : null,
                ])->values()->all();
            }),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
