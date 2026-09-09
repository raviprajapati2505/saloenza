<?php

namespace App\Support\Catalog;

use App\Models\SalonServiceProduct;
use Illuminate\Database\Eloquent\Builder;

final class SalonOfferingResolver
{
    public static function find(
        int $saloonId,
        int $serviceId,
        ?int $productId = null,
        ?int $branchId = null,
    ): ?SalonServiceProduct {
        $offering = self::query($saloonId, $serviceId, $productId, $branchId)->first();

        if ($offering === null && $productId !== null) {
            $offering = self::query($saloonId, $serviceId, null, $branchId)->first();
        }

        if ($offering === null && $productId === null) {
            $offering = self::query($saloonId, $serviceId, null, $branchId, anyProduct: true)->first();
        }

        return $offering;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function bookableServices(int $saloonId, ?int $branchId = null, ?int $excludeServiceId = null): array
    {
        $offerings = SalonServiceProduct::query()
            ->with(['service', 'product'])
            ->where('saloon_id', $saloonId)
            ->where('is_active', true)
            ->when($branchId !== null, fn (Builder $query) => $query->where(function (Builder $inner) use ($branchId): void {
                $inner->whereNull('branch_id')->orWhere('branch_id', $branchId);
            }))
            ->when($excludeServiceId !== null, fn (Builder $query) => $query->where('service_id', '!=', $excludeServiceId))
            ->get();

        return $offerings
            ->filter(fn (SalonServiceProduct $row): bool => $row->service !== null)
            ->groupBy('service_id')
            ->map(function ($rows) {
                /** @var SalonServiceProduct $first */
                $first = $rows->first();
                $baseRow = $rows->firstWhere('product_id', null) ?? $first;

                $products = $rows
                    ->filter(fn (SalonServiceProduct $row): bool => $row->product_id !== null && $row->product !== null)
                    ->unique('product_id')
                    ->map(fn (SalonServiceProduct $row): array => [
                        'product_id' => $row->product_id,
                        'name' => $row->product?->name,
                        'price' => $row->price,
                        'duration_minutes' => $row->duration_minutes,
                    ])
                    ->values();

                return [
                    'service_id' => $first->service_id,
                    'name' => $first->service?->name,
                    'price' => $baseRow->price,
                    'duration_minutes' => $baseRow->duration_minutes,
                    'products' => $products->all(),
                ];
            })
            ->sortBy('name')
            ->values()
            ->all();
    }

    private static function query(
        int $saloonId,
        int $serviceId,
        ?int $productId,
        ?int $branchId,
        bool $anyProduct = false,
    ): Builder {
        return SalonServiceProduct::query()
            ->where('saloon_id', $saloonId)
            ->where('service_id', $serviceId)
            ->where('is_active', true)
            ->when(
                ! $anyProduct,
                fn (Builder $query) => $productId === null
                    ? $query->whereNull('product_id')
                    : $query->where('product_id', $productId),
            )
            ->when($branchId !== null, fn (Builder $query) => $query->where(function (Builder $inner) use ($branchId): void {
                $inner->whereNull('branch_id')->orWhere('branch_id', $branchId);
            }))
            ->orderByRaw('CASE WHEN product_id IS NULL THEN 0 ELSE 1 END')
            ->orderByRaw('CASE WHEN branch_id IS NULL THEN 0 ELSE 1 END');
    }
}
