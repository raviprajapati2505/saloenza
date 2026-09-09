<?php

namespace App\Services\Inventory;

use App\Models\BranchProductStock;
use App\Models\InventoryMovement;
use App\Support\Inventory\InventoryMovementType;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class InventoryMovementService
{
    public function record(
        int $saloonId,
        int $branchId,
        int $productId,
        string $type,
        int $quantityChange,
        int $quantityAfter,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $notes = null,
        ?int $createdBy = null,
    ): InventoryMovement {
        return InventoryMovement::query()->create([
            'saloon_id' => $saloonId,
            'branch_id' => $branchId,
            'product_id' => $productId,
            'type' => $type,
            'quantity_change' => $quantityChange,
            'quantity_after' => $quantityAfter,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'notes' => $notes,
            'created_by' => $createdBy,
        ]);
    }
}
