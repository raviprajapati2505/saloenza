<?php

namespace App\Services\Inventory;

use App\Models\BranchProductStock;
use App\Models\Product;
use App\Models\SalonServiceProduct;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Resolves which stocked products a branch may sell over the counter and at what price.
 *
 * A product becomes sellable as soon as it has a branch stock row carrying a
 * selling price; the legacy salon service/product catalog is only consulted as a
 * fallback so existing retail offerings keep working.
 */
class RetailProductService
{
    /**
     * Sellable retail catalog for a branch, including out-of-stock rows so the
     * cashier can see why an item cannot be added.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function catalogForBranch(int $saloonId, int $branchId): Collection
    {
        $fallbackPrices = $this->fallbackPrices($saloonId, $branchId);

        return BranchProductStock::query()
            ->with(['product.category'])
            ->where('branch_id', $branchId)
            ->whereHas('product', fn ($query) => $query->where('is_active', true))
            ->get()
            ->map(function (BranchProductStock $stock) use ($fallbackPrices): ?array {
                $price = $stock->selling_price !== null
                    ? (float) $stock->selling_price
                    : $fallbackPrices->get($stock->product_id);

                if ($price === null) {
                    return null;
                }

                return [
                    'product_id' => (int) $stock->product_id,
                    'name' => $stock->product?->name,
                    'sku' => $stock->product?->sku,
                    'brand' => $stock->product?->brand,
                    'unit' => $stock->product?->unit,
                    'category' => $stock->product?->category?->name,
                    'price' => round((float) $price, 2),
                    'cost_price' => $stock->cost_price !== null ? (float) $stock->cost_price : null,
                    'stock_on_hand' => (int) $stock->quantity_on_hand,
                    'reorder_level' => (int) $stock->reorder_level,
                ];
            })
            ->filter()
            ->sortBy('name')
            ->values();
    }

    /**
     * Resolve the price/cost snapshot for a single sold product line.
     *
     * @return array{unit_price: float, unit_cost: float|null}
     */
    public function resolveLine(
        int $saloonId,
        ?int $branchId,
        int $productId,
        ?float $overridePrice,
        string $errorKey,
    ): array {
        $product = Product::query()->find($productId);

        if ($product === null || ! $product->is_active) {
            throw ValidationException::withMessages([
                $errorKey => 'This product is not available for sale.',
            ]);
        }

        $stock = $branchId !== null
            ? BranchProductStock::query()
                ->where('branch_id', $branchId)
                ->where('product_id', $productId)
                ->first()
            : null;

        $unitPrice = $overridePrice;

        if ($unitPrice === null && $stock?->selling_price !== null) {
            $unitPrice = (float) $stock->selling_price;
        }

        if ($unitPrice === null) {
            $unitPrice = $this->fallbackPrice($saloonId, $branchId, $productId);
        }

        if ($unitPrice === null) {
            throw ValidationException::withMessages([
                $errorKey => 'No retail price is configured for this product at the selected branch.',
            ]);
        }

        return [
            'unit_price' => round((float) $unitPrice, 2),
            'unit_cost' => $stock?->cost_price !== null ? round((float) $stock->cost_price, 2) : null,
        ];
    }

    /**
     * Legacy retail prices held on the salon service/product catalog.
     *
     * @return Collection<int, float>
     */
    private function fallbackPrices(int $saloonId, int $branchId): Collection
    {
        return SalonServiceProduct::query()
            ->where('saloon_id', $saloonId)
            ->where('is_active', true)
            ->whereNotNull('product_id')
            ->where(fn ($query) => $query->whereNull('branch_id')->orWhere('branch_id', $branchId))
            ->orderByRaw('branch_id is null')
            ->get(['product_id', 'price'])
            ->groupBy('product_id')
            ->map(fn (Collection $rows): float => (float) $rows->first()->price);
    }

    private function fallbackPrice(int $saloonId, ?int $branchId, int $productId): ?float
    {
        $price = SalonServiceProduct::query()
            ->where('saloon_id', $saloonId)
            ->where('is_active', true)
            ->where('product_id', $productId)
            ->when(
                $branchId !== null,
                fn ($query) => $query->where(
                    fn ($inner) => $inner->whereNull('branch_id')->orWhere('branch_id', $branchId),
                ),
            )
            ->orderByRaw('branch_id is null')
            ->value('price');

        return $price !== null ? (float) $price : null;
    }
}
