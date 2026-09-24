<?php

namespace App\Http\Resources\Api\V1\Package;

use App\Models\CustomerPackage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CustomerPackage */
class CustomerPackageResource extends JsonResource
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
            'customer_id' => $this->customer_id,
            'package_id' => $this->package_id,
            'purchased_at' => $this->purchased_at?->toISOString(),
            'expires_at' => $this->expires_at?->toISOString(),
            'status' => $this->status,
            'amount_paid' => $this->amount_paid,
            'payment_method' => $this->payment_method,
            'payment_ref' => $this->payment_ref,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toISOString(),
            'package' => $this->whenLoaded('package', fn () => $this->package ? [
                'id' => $this->package->id,
                'name' => $this->package->name,
                'price' => $this->package->price,
                'valid_days' => $this->package->valid_days,
            ] : null),
            'customer' => $this->whenLoaded('customer', fn () => $this->customer ? [
                'id' => $this->customer->id,
                'name' => $this->customer->name,
                'phone' => $this->customer->phone,
                'email' => $this->customer->email,
            ] : null),
            'seller' => $this->whenLoaded('seller', fn () => $this->seller ? [
                'id' => $this->seller->id,
                'name' => $this->seller->name,
            ] : null),
            'branch' => $this->whenLoaded('branch', fn () => $this->branch ? [
                'id' => $this->branch->id,
                'branch_name' => $this->branch->branch_name,
            ] : null),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'id' => $item->id,
                'service_id' => $item->service_id,
                'quantity_total' => $item->quantity_total,
                'quantity_remaining' => $item->quantity_remaining,
                'unit_value' => $item->unit_value,
                'service' => $item->relationLoaded('service') && $item->service ? [
                    'id' => $item->service->id,
                    'name' => $item->service->name,
                ] : null,
            ])->values()->all()),
            'remaining_total' => $this->whenLoaded(
                'items',
                fn () => (int) $this->items->sum('quantity_remaining'),
            ),
        ];
    }
}
