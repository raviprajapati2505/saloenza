<?php

namespace App\Services\Inventory;

use App\Models\AppointmentProduct;
use App\Models\BranchProductStock;
use App\Models\InventoryAlert;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class InventoryIntelligenceService
{
    private const TARGET_DAYS_OF_COVER = 14;

    private const STOCKOUT_RISK_DAYS = 7;

    /**
     * Recompute velocity metrics for every stock row belonging to the salon.
     *
     * @return array{updated: int, alerts_opened: int, alerts_resolved: int}
     */
    public function recomputeMetrics(int $saloonId): array
    {
        $now = Carbon::now();
        $from90 = $now->copy()->subDays(89)->startOfDay();
        $to = $now->copy()->endOfDay();

        $stocks = BranchProductStock::query()
            ->with('product')
            ->whereHas('branch', fn (Builder $query) => $query->where('saloon_id', $saloonId))
            ->get();

        $lines = AppointmentProduct::query()
            ->with('appointment')
            ->whereHas('appointment', function (Builder $query) use ($saloonId, $from90, $to): void {
                $query
                    ->where('saloon_id', $saloonId)
                    ->where('status', 'completed')
                    ->where('starts_at', '>=', $from90)
                    ->where('starts_at', '<=', $to);
            })
            ->get();

        $unitsByKey = $this->aggregateUnitsByWindow($lines, $now);

        $updated = 0;
        $alertsOpened = 0;
        $openKeys = [];

        foreach ($stocks as $stock) {
            $key = $this->stockKey((int) $stock->branch_id, (int) $stock->product_id);
            $bucket = $unitsByKey[$key] ?? ['7' => 0, '30' => 0, '90' => 0];

            $avg7 = round($bucket['7'] / 7, 4);
            $avg30 = round($bucket['30'] / 30, 4);
            $avg90 = round($bucket['90'] / 90, 4);
            $effective = $avg30 > 0 ? $avg30 : ($avg90 > 0 ? $avg90 : $avg7);
            $qty = (int) $stock->quantity_on_hand;
            $velocity = $this->classifyVelocity($avg30 > 0 ? $avg30 : $effective);
            $daysOfCover = $effective > 0 ? round($qty / $effective, 2) : null;
            $daysToStockout = $daysOfCover;
            $suggested = $this->computeSuggestedReorderQty($effective, $qty, $stock->max_stock !== null ? (int) $stock->max_stock : null);

            $stock->fill([
                'avg_daily_sales_7d' => $avg7,
                'avg_daily_sales_30d' => $avg30,
                'avg_daily_sales_90d' => $avg90,
                'days_of_cover' => $daysOfCover,
                'days_to_stockout' => $daysToStockout,
                'velocity_class' => $velocity,
                'suggested_reorder_qty' => $suggested,
                'metrics_updated_at' => $now,
            ]);
            $stock->save();
            $updated++;

            $alertsOpened += $this->syncAlertsForStock($saloonId, $stock, $openKeys);
        }

        $resolveQuery = InventoryAlert::query()
            ->forSaloon($saloonId)
            ->open()
            ->whereIn('type', [
                InventoryAlert::TYPE_STOCKOUT,
                InventoryAlert::TYPE_STOCKOUT_RISK,
                InventoryAlert::TYPE_SLOW_MOVER,
            ]);

        if ($openKeys !== []) {
            $resolveQuery->whereNotIn('id', $openKeys);
        }

        $alertsResolved = $resolveQuery->update(['status' => InventoryAlert::STATUS_RESOLVED]);

        return [
            'updated' => $updated,
            'alerts_opened' => $alertsOpened,
            'alerts_resolved' => (int) $alertsResolved,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listClassifications(int $saloonId, ?int $branchId = null): array
    {
        $rows = BranchProductStock::query()
            ->with(['product.category', 'branch'])
            ->whereHas('branch', fn (Builder $query) => $query->where('saloon_id', $saloonId))
            ->when($branchId !== null, fn (Builder $query) => $query->where('branch_id', $branchId))
            ->whereNotNull('velocity_class')
            ->orderByRaw("CASE velocity_class WHEN 'fast' THEN 1 WHEN 'medium' THEN 2 WHEN 'slow' THEN 3 ELSE 4 END")
            ->orderBy('product_id')
            ->get();

        return $rows->map(fn (BranchProductStock $row) => $this->stockPayload($row))->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function stockoutRisks(int $saloonId, ?int $branchId = null): array
    {
        $rows = BranchProductStock::query()
            ->with(['product.category', 'branch'])
            ->whereHas('branch', fn (Builder $query) => $query->where('saloon_id', $saloonId))
            ->when($branchId !== null, fn (Builder $query) => $query->where('branch_id', $branchId))
            ->where(function (Builder $query): void {
                $query
                    ->where('quantity_on_hand', '<=', 0)
                    ->orWhere(function (Builder $inner): void {
                        $inner
                            ->whereNotNull('days_to_stockout')
                            ->where('days_to_stockout', '<=', self::STOCKOUT_RISK_DAYS);
                    });
            })
            ->orderByRaw('CASE WHEN quantity_on_hand <= 0 THEN 0 ELSE 1 END')
            ->orderBy('days_to_stockout')
            ->get();

        return $rows->map(fn (BranchProductStock $row) => $this->stockPayload($row))->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function suggestReorderQty(int $saloonId, ?int $branchId = null): array
    {
        $rows = BranchProductStock::query()
            ->with(['product.category', 'branch'])
            ->whereHas('branch', fn (Builder $query) => $query->where('saloon_id', $saloonId))
            ->when($branchId !== null, fn (Builder $query) => $query->where('branch_id', $branchId))
            ->where('suggested_reorder_qty', '>', 0)
            ->orderByDesc('suggested_reorder_qty')
            ->get();

        return $rows->map(fn (BranchProductStock $row) => $this->stockPayload($row))->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function openAlerts(int $saloonId, ?int $branchId = null): array
    {
        $rows = InventoryAlert::query()
            ->with(['product', 'branch'])
            ->forSaloon($saloonId)
            ->open()
            ->when($branchId !== null, fn (Builder $query) => $query->where('branch_id', $branchId))
            ->orderByRaw("CASE severity WHEN 'critical' THEN 1 WHEN 'high' THEN 2 WHEN 'medium' THEN 3 ELSE 4 END")
            ->orderByDesc('created_at')
            ->get();

        return $rows->map(fn (InventoryAlert $alert) => [
            'id' => $alert->id,
            'type' => $alert->type,
            'severity' => $alert->severity,
            'message' => $alert->message,
            'status' => $alert->status,
            'metrics' => $alert->metrics_json,
            'branch' => $alert->branch ? [
                'id' => $alert->branch->id,
                'name' => $alert->branch->branch_name,
            ] : null,
            'product' => $alert->product ? [
                'id' => $alert->product->id,
                'name' => $alert->product->name,
            ] : null,
            'created_at' => $alert->created_at?->toISOString(),
        ])->all();
    }

    /**
     * @param  Collection<int, AppointmentProduct>  $lines
     * @return array<string, array{7: int, 30: int, 90: int}>
     */
    private function aggregateUnitsByWindow(Collection $lines, Carbon $now): array
    {
        $cut7 = $now->copy()->subDays(6)->startOfDay();
        $cut30 = $now->copy()->subDays(29)->startOfDay();
        $map = [];

        foreach ($lines as $line) {
            $appointment = $line->appointment;
            if ($appointment === null || $appointment->starts_at === null) {
                continue;
            }

            $branchId = (int) ($line->branch_id ?: $appointment->branch_id);
            $productId = (int) $line->product_id;
            $key = $this->stockKey($branchId, $productId);
            if (! isset($map[$key])) {
                $map[$key] = ['7' => 0, '30' => 0, '90' => 0];
            }

            $qty = max(0, (int) $line->quantity);
            $starts = $appointment->starts_at;
            $map[$key]['90'] += $qty;
            if ($starts->gte($cut30)) {
                $map[$key]['30'] += $qty;
            }
            if ($starts->gte($cut7)) {
                $map[$key]['7'] += $qty;
            }
        }

        return $map;
    }

    private function classifyVelocity(float $avgDaily): string
    {
        if ($avgDaily >= 1.0) {
            return 'fast';
        }
        if ($avgDaily >= 0.3) {
            return 'medium';
        }
        if ($avgDaily > 0) {
            return 'slow';
        }

        return 'dormant';
    }

    private function computeSuggestedReorderQty(float $avgDaily, int $qtyOnHand, ?int $maxStock): int
    {
        if ($avgDaily <= 0) {
            return 0;
        }

        $target = (int) ceil($avgDaily * self::TARGET_DAYS_OF_COVER);
        $suggested = max(0, $target - $qtyOnHand);

        if ($maxStock !== null) {
            $suggested = min($suggested, max(0, $maxStock - $qtyOnHand));
        }

        return $suggested;
    }

    /**
     * @param  list<int>  $openKeys
     */
    private function syncAlertsForStock(int $saloonId, BranchProductStock $stock, array &$openKeys): int
    {
        $opened = 0;
        $qty = (int) $stock->quantity_on_hand;
        $days = $stock->days_to_stockout !== null ? (float) $stock->days_to_stockout : null;
        $productName = $stock->product?->name ?? ('Product #'.$stock->product_id);
        $candidates = [];

        if ($qty <= 0) {
            $candidates[] = [
                'type' => InventoryAlert::TYPE_STOCKOUT,
                'severity' => 'critical',
                'message' => "{$productName} is out of stock.",
            ];
        } elseif ($days !== null && $days <= self::STOCKOUT_RISK_DAYS) {
            $candidates[] = [
                'type' => InventoryAlert::TYPE_STOCKOUT_RISK,
                'severity' => $days <= 3 ? 'high' : 'medium',
                'message' => "{$productName} may stock out in about {$days} day(s).",
            ];
        }

        if (in_array($stock->velocity_class, ['slow', 'dormant'], true) && $qty > 0) {
            $candidates[] = [
                'type' => InventoryAlert::TYPE_SLOW_MOVER,
                'severity' => 'low',
                'message' => "{$productName} is a {$stock->velocity_class} mover with {$qty} unit(s) on hand.",
            ];
        }

        foreach ($candidates as $candidate) {
            $alert = InventoryAlert::query()->firstOrNew([
                'saloon_id' => $saloonId,
                'branch_id' => (int) $stock->branch_id,
                'product_id' => (int) $stock->product_id,
                'type' => $candidate['type'],
                'status' => InventoryAlert::STATUS_OPEN,
            ]);

            $wasNew = ! $alert->exists;
            $alert->fill([
                'severity' => $candidate['severity'],
                'message' => $candidate['message'],
                'metrics_json' => [
                    'quantity_on_hand' => $qty,
                    'days_to_stockout' => $days,
                    'velocity_class' => $stock->velocity_class,
                    'suggested_reorder_qty' => $stock->suggested_reorder_qty,
                    'avg_daily_sales_30d' => $stock->avg_daily_sales_30d,
                ],
            ]);
            $alert->save();
            $openKeys[] = (int) $alert->id;
            if ($wasNew) {
                $opened++;
            }
        }

        return $opened;
    }

    /**
     * @return array<string, mixed>
     */
    private function stockPayload(BranchProductStock $row): array
    {
        return [
            'id' => $row->id,
            'branch_id' => $row->branch_id,
            'product_id' => $row->product_id,
            'quantity_on_hand' => (int) $row->quantity_on_hand,
            'reorder_level' => (int) $row->reorder_level,
            'avg_daily_sales_7d' => $row->avg_daily_sales_7d !== null ? (float) $row->avg_daily_sales_7d : null,
            'avg_daily_sales_30d' => $row->avg_daily_sales_30d !== null ? (float) $row->avg_daily_sales_30d : null,
            'avg_daily_sales_90d' => $row->avg_daily_sales_90d !== null ? (float) $row->avg_daily_sales_90d : null,
            'days_of_cover' => $row->days_of_cover !== null ? (float) $row->days_of_cover : null,
            'days_to_stockout' => $row->days_to_stockout !== null ? (float) $row->days_to_stockout : null,
            'velocity_class' => $row->velocity_class,
            'suggested_reorder_qty' => $row->suggested_reorder_qty !== null ? (int) $row->suggested_reorder_qty : null,
            'metrics_updated_at' => $row->metrics_updated_at?->toISOString(),
            'branch' => $row->relationLoaded('branch') && $row->branch ? [
                'id' => $row->branch->id,
                'name' => $row->branch->branch_name,
            ] : null,
            'product' => $row->product ? [
                'id' => $row->product->id,
                'name' => $row->product->name,
                'sku' => $row->product->sku,
                'category' => $row->product->relationLoaded('category') && $row->product->category ? [
                    'id' => $row->product->category->id,
                    'name' => $row->product->category->name,
                ] : null,
            ] : null,
        ];
    }

    private function stockKey(int $branchId, int $productId): string
    {
        return $branchId.':'.$productId;
    }
}
