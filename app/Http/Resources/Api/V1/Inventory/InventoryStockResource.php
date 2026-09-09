<?php

namespace App\Http\Resources\Api\V1\Inventory;

use App\Models\BranchProductStock;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin BranchProductStock */
class InventoryStockResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $product = $this->product;
        $qty = (int) $this->quantity_on_hand;
        $reorder = (int) $this->reorder_level;
        $max = (int) ($this->max_stock ?? 100);

        return [
            'id' => $this->id,
            'branch_id' => $this->branch_id,
            'product_id' => $this->product_id,
            'quantity_on_hand' => $qty,
            'reorder_level' => $reorder,
            'max_stock' => $max,
            'cost_price' => $this->cost_price,
            'selling_price' => $this->selling_price,
            'supplier_id' => $this->supplier_id,
            'stock_status' => $qty <= 0 ? 'out' : ($qty <= $reorder ? 'low' : ($qty >= $max * 0.8 ? 'high' : 'ok')),
            'product' => $product ? [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'brand' => $product->brand,
                'unit' => $product->unit,
                'category' => $product->relationLoaded('category') && $product->category ? [
                    'id' => $product->category->id,
                    'name' => $product->category->name,
                ] : null,
            ] : null,
            'supplier' => $this->whenLoaded('supplier', fn () => $this->supplier ? [
                'id' => $this->supplier->id,
                'name' => $this->supplier->name,
            ] : null),
        ];
    }
}
