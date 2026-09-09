<?php

namespace App\Services\Inventory;

use App\Models\BranchProductStock;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\User;
use App\Support\Inventory\PurchaseOrderStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseOrderService
{
    public function __construct(
        private readonly BranchStockService $stock,
    ) {
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function create(User $actor, array $payload): PurchaseOrder
    {
        return DB::transaction(function () use ($actor, $payload): PurchaseOrder {
            $po = PurchaseOrder::query()->create([
                'saloon_id' => (int) $actor->saloon_id,
                'branch_id' => (int) $payload['branch_id'],
                'supplier_id' => $payload['supplier_id'] ?? null,
                'po_number' => $this->nextPoNumber((int) $actor->saloon_id),
                'status' => PurchaseOrderStatus::DRAFT,
                'notes' => $payload['notes'] ?? null,
                'created_by' => $actor->id,
            ]);

            foreach ($payload['items'] as $item) {
                $po->items()->create([
                    'product_id' => (int) $item['product_id'],
                    'quantity_ordered' => max(1, (int) $item['quantity_ordered']),
                    'unit_cost' => (float) ($item['unit_cost'] ?? 0),
                ]);
            }

            return $po->fresh(['items.product', 'supplier', 'branch']);
        });
    }

    public function placeOrder(PurchaseOrder $order): PurchaseOrder
    {
        if ($order->status !== PurchaseOrderStatus::DRAFT) {
            throw ValidationException::withMessages([
                'status' => 'Only draft purchase orders can be placed.',
            ]);
        }

        if ($order->items()->count() === 0) {
            throw ValidationException::withMessages([
                'items' => 'Add at least one line item.',
            ]);
        }

        $order->update([
            'status' => PurchaseOrderStatus::ORDERED,
            'ordered_at' => now(),
        ]);

        return $order->fresh(['items.product', 'supplier', 'branch']);
    }

    public function receive(PurchaseOrder $order, User $actor, ?array $lines = null): PurchaseOrder
    {
        if (! in_array($order->status, [PurchaseOrderStatus::ORDERED, PurchaseOrderStatus::DRAFT], true)) {
            throw ValidationException::withMessages([
                'status' => 'This purchase order cannot be received.',
            ]);
        }

        return DB::transaction(function () use ($order, $actor, $lines): PurchaseOrder {
            $order->load('items.product');

            foreach ($order->items as $item) {
                $remaining = $item->quantity_ordered - $item->quantity_received;
                $receiveQty = $remaining;

                if ($lines !== null && isset($lines[$item->id]['quantity'])) {
                    $receiveQty = (int) $lines[$item->id]['quantity'];
                }

                $receiveQty = max(0, $receiveQty);

                if ($receiveQty <= 0 || $receiveQty > $remaining) {
                    continue;
                }

                $this->stock->receive(
                    (int) $order->saloon_id,
                    (int) $order->branch_id,
                    (int) $item->product_id,
                    $receiveQty,
                    (int) $actor->id,
                    'purchase_order',
                    (int) $order->id,
                    'PO '.$order->po_number,
                );

                BranchProductStock::query()
                    ->where('branch_id', $order->branch_id)
                    ->where('product_id', $item->product_id)
                    ->update(array_filter([
                        'supplier_id' => $order->supplier_id,
                        'cost_price' => $item->unit_cost > 0 ? $item->unit_cost : null,
                    ]));

                $item->update([
                    'quantity_received' => $item->quantity_received + $receiveQty,
                ]);
            }

            $allReceived = $order->items()->get()->every(
                fn (PurchaseOrderItem $item) => $item->quantity_received >= $item->quantity_ordered,
            );

            $order->update([
                'status' => $allReceived ? PurchaseOrderStatus::RECEIVED : PurchaseOrderStatus::ORDERED,
                'received_at' => $allReceived ? now() : $order->received_at,
                'ordered_at' => $order->ordered_at ?? now(),
            ]);

            return $order->fresh(['items.product', 'supplier', 'branch']);
        });
    }

    public function cancel(PurchaseOrder $order): PurchaseOrder
    {
        if ($order->status === PurchaseOrderStatus::RECEIVED) {
            throw ValidationException::withMessages([
                'status' => 'Received purchase orders cannot be cancelled.',
            ]);
        }

        $order->update(['status' => PurchaseOrderStatus::CANCELLED]);

        return $order->fresh(['items.product', 'supplier', 'branch']);
    }

    private function nextPoNumber(int $saloonId): string
    {
        $count = PurchaseOrder::query()->where('saloon_id', $saloonId)->count() + 1;

        return 'PO-'.str_pad((string) $count, 5, '0', STR_PAD_LEFT);
    }
}
