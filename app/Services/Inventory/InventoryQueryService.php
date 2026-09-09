<?php

namespace App\Services\Inventory;

use App\Models\BranchProductStock;
use App\Models\InventoryMovement;
use Illuminate\Database\Eloquent\Builder;

class InventoryQueryService
{
    public function __construct(
        private readonly BranchStockService $stock,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(int $branchId): array
    {
        $rows = $this->stock->forBranch($branchId);

        $totalSkus = $rows->count();
        $lowStock = $rows->filter(fn (BranchProductStock $row) => $row->quantity_on_hand > 0 && $row->quantity_on_hand <= $row->reorder_level)->count();
        $outOfStock = $rows->filter(fn (BranchProductStock $row) => $row->quantity_on_hand <= 0)->count();
        $inventoryValue = $rows->sum(fn (BranchProductStock $row) => (float) ($row->cost_price ?? 0) * $row->quantity_on_hand);

        return [
            'total_skus' => $totalSkus,
            'low_stock_count' => $lowStock,
            'out_of_stock_count' => $outOfStock,
            'inventory_value' => round($inventoryValue, 2),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<BranchProductStock>
     */
    public function stockQuery(int $branchId, array $filters = []): Builder
    {
        $query = BranchProductStock::query()
            ->with(['product.category', 'supplier'])
            ->where('branch_id', $branchId);

        if (! empty($filters['search'])) {
            $search = (string) $filters['search'];
            $query->whereHas('product', function (Builder $productQuery) use ($search): void {
                $productQuery->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%")
                    ->orWhere('brand', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['category_id'])) {
            $categoryId = (int) $filters['category_id'];
            $query->whereHas('product', fn (Builder $productQuery) => $productQuery->where('category_id', $categoryId));
        }

        if (! empty($filters['low_stock'])) {
            $query->whereColumn('quantity_on_hand', '<=', 'reorder_level');
        }

        if (! empty($filters['out_of_stock'])) {
            $query->where('quantity_on_hand', '<=', 0);
        }

        return $query->orderBy('product_id');
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<InventoryMovement>
     */
    public function movementQuery(int $saloonId, array $filters = []): Builder
    {
        $query = InventoryMovement::query()
            ->with(['product', 'creator'])
            ->where('saloon_id', $saloonId)
            ->latest('id');

        if (! empty($filters['branch_id'])) {
            $query->where('branch_id', (int) $filters['branch_id']);
        }

        if (! empty($filters['product_id'])) {
            $query->where('product_id', (int) $filters['product_id']);
        }

        return $query;
    }
}
