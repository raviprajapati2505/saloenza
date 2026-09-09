<?php

namespace App\Services\Inventory;

use App\Models\BranchProductStock;
use App\Support\Inventory\InventoryMovementType;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class BranchStockService
{
    public function __construct(
        private readonly InventoryMovementService $movements,
    ) {}

    /**
     * @return Collection<int, BranchProductStock>
     */
    public function forBranch(int $branchId): Collection
    {
        return BranchProductStock::query()
            ->with(['product.category', 'supplier'])
            ->where('branch_id', $branchId)
            ->orderBy('product_id')
            ->get();
    }

    public function quantityOnHand(int $branchId, int $productId): int
    {
        return (int) (BranchProductStock::query()
            ->where('branch_id', $branchId)
            ->where('product_id', $productId)
            ->value('quantity_on_hand') ?? 0);
    }

    /**
     * @param  list<array{product_id: int, quantity: int}>  $lines
     */
    public function assertAvailable(int $branchId, array $lines, string $errorBag = 'items'): void
    {
        foreach ($lines as $index => $line) {
            $productId = (int) $line['product_id'];
            $quantity = max(1, (int) $line['quantity']);
            $available = $this->quantityOnHand($branchId, $productId);

            if ($available < $quantity) {
                throw ValidationException::withMessages([
                    "{$errorBag}.{$index}.quantity" => "Insufficient stock ({$available} available).",
                ]);
            }
        }
    }

    /**
     * @param  list<array{product_id: int, quantity: int}>  $lines
     */
    public function deduct(
        int $saloonId,
        int $branchId,
        array $lines,
        ?int $userId = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
    ): void {
        foreach ($lines as $line) {
            $this->applyChange(
                $saloonId,
                $branchId,
                (int) $line['product_id'],
                -max(1, (int) $line['quantity']),
                InventoryMovementType::SALE,
                $referenceType,
                $referenceId,
                null,
                $userId,
            );
        }
    }

    public function receive(
        int $saloonId,
        int $branchId,
        int $productId,
        int $quantity,
        ?int $userId = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $notes = null,
    ): BranchProductStock {
        return $this->applyChange(
            $saloonId,
            $branchId,
            $productId,
            max(1, $quantity),
            InventoryMovementType::PURCHASE,
            $referenceType,
            $referenceId,
            $notes,
            $userId,
        );
    }

    public function adjust(
        int $saloonId,
        int $branchId,
        int $productId,
        int $delta,
        ?int $userId = null,
        ?string $notes = null,
    ): BranchProductStock {
        if ($delta === 0) {
            throw ValidationException::withMessages([
                'quantity_change' => 'Adjustment quantity cannot be zero.',
            ]);
        }

        return $this->applyChange(
            $saloonId,
            $branchId,
            $productId,
            $delta,
            InventoryMovementType::ADJUSTMENT,
            null,
            null,
            $notes,
            $userId,
        );
    }

    public function upsert(
        int $branchId,
        int $productId,
        int $quantity,
        int $reorderLevel = 5,
        array $extras = [],
    ): BranchProductStock {
        return BranchProductStock::query()->updateOrCreate(
            [
                'branch_id' => $branchId,
                'product_id' => $productId,
            ],
            array_merge([
                'quantity_on_hand' => max(0, $quantity),
                'reorder_level' => max(0, $reorderLevel),
            ], $extras),
        );
    }

    public function applyChange(
        int $saloonId,
        int $branchId,
        int $productId,
        int $delta,
        string $type,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $notes = null,
        ?int $userId = null,
    ): BranchProductStock {
        $stock = BranchProductStock::query()
            ->where('branch_id', $branchId)
            ->where('product_id', $productId)
            ->lockForUpdate()
            ->first();

        if ($stock === null) {
            if ($delta < 0) {
                throw ValidationException::withMessages([
                    'product_id' => 'No stock record exists for this product at the branch.',
                ]);
            }

            $stock = BranchProductStock::query()->create([
                'branch_id' => $branchId,
                'product_id' => $productId,
                'quantity_on_hand' => 0,
                'reorder_level' => 5,
                'max_stock' => 100,
            ]);
        }

        $newQty = $stock->quantity_on_hand + $delta;
        if ($newQty < 0) {
            throw ValidationException::withMessages([
                'quantity' => 'Insufficient stock for this operation.',
            ]);
        }

        $stock->update(['quantity_on_hand' => $newQty]);

        $this->movements->record(
            $saloonId,
            $branchId,
            $productId,
            $type,
            $delta,
            $newQty,
            $referenceType,
            $referenceId,
            $notes,
            $userId,
        );

        return $stock->fresh(['product.category', 'supplier']);
    }
}
