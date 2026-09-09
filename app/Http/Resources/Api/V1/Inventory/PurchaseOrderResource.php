<?php

namespace App\Http\Resources\Api\V1\Inventory;

use App\Models\PurchaseOrder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PurchaseOrder */
class PurchaseOrderResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'po_number' => $this->po_number,
            'status' => $this->status,
            'branch_id' => $this->branch_id,
            'supplier_id' => $this->supplier_id,
            'ordered_at' => $this->ordered_at?->toISOString(),
            'received_at' => $this->received_at?->toISOString(),
            'notes' => $this->notes,
            'supplier' => $this->whenLoaded('supplier', fn () => $this->supplier ? (new SupplierResource($this->supplier))->resolve() : null),
            'branch' => $this->whenLoaded('branch', fn () => $this->branch ? [
                'id' => $this->branch->id,
                'branch_name' => $this->branch->branch_name,
            ] : null),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'quantity_ordered' => $item->quantity_ordered,
                'quantity_received' => $item->quantity_received,
                'unit_cost' => $item->unit_cost,
                'product' => $item->relationLoaded('product') && $item->product ? [
                    'id' => $item->product->id,
                    'name' => $item->product->name,
                    'sku' => $item->product->sku,
                ] : null,
            ])->values()->all()),
        ];
    }
}
