<?php

namespace App\Services\Analytics;

use App\Models\Appointment;
use App\Models\AppointmentProduct;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Retail reporting for products sold at the counter or added onto a visit.
 *
 * Only completed visits count as a sale, which keeps this aligned with the
 * point at which stock actually leaves the branch.
 */
class ProductSalesReportService
{
    /**
     * @return array<string, mixed>
     */
    public function summary(?int $saloonId, ?int $branchId, string $from, string $to): array
    {
        $lines = $this->lines($saloonId, $branchId, $from, $to);

        $unitsSold = 0;
        $revenue = 0.0;
        $cost = 0.0;
        // Margin is only meaningful for lines that captured a cost snapshot.
        $revenueWithCost = 0.0;
        $standaloneRevenue = 0.0;
        $attachedRevenue = 0.0;
        $productMap = [];
        $categoryMap = [];
        $staffMap = [];
        $dailyRevenue = [];
        $dailyUnits = [];
        $transactions = [];

        foreach ($lines as $line) {
            $appointment = $line->appointment;
            $quantity = max(1, (int) $line->quantity);
            $lineTotal = (float) $line->line_total;
            $lineCost = $line->unit_cost !== null ? (float) $line->unit_cost * $quantity : null;

            $unitsSold += $quantity;
            $revenue += $lineTotal;

            if ($lineCost !== null) {
                $cost += $lineCost;
                $revenueWithCost += $lineTotal;
            }

            $transactions[(int) $line->appointment_id] = true;

            if ($appointment?->type === Appointment::TYPE_PRODUCT_SALE) {
                $standaloneRevenue += $lineTotal;
            } else {
                $attachedRevenue += $lineTotal;
            }

            $productKey = (int) $line->product_id;
            if (! isset($productMap[$productKey])) {
                $productMap[$productKey] = [
                    'product_id' => $productKey,
                    'name' => $line->product?->name ?? 'Product',
                    'sku' => $line->product?->sku,
                    'category' => $line->product?->category?->name,
                    'units' => 0,
                    'revenue' => 0.0,
                    'cost' => 0.0,
                ];
            }
            $productMap[$productKey]['units'] += $quantity;
            $productMap[$productKey]['revenue'] += $lineTotal;
            $productMap[$productKey]['cost'] += $lineCost ?? 0.0;

            $categoryKey = $line->product?->category?->name ?? 'Uncategorised';
            if (! isset($categoryMap[$categoryKey])) {
                $categoryMap[$categoryKey] = ['name' => $categoryKey, 'units' => 0, 'revenue' => 0.0];
            }
            $categoryMap[$categoryKey]['units'] += $quantity;
            $categoryMap[$categoryKey]['revenue'] += $lineTotal;

            $staffKey = $line->staff_id !== null ? (int) $line->staff_id : 0;
            if (! isset($staffMap[$staffKey])) {
                $staffMap[$staffKey] = [
                    'staff_id' => $line->staff_id !== null ? (int) $line->staff_id : null,
                    'name' => $line->staff?->name ?? 'Unassigned',
                    'units' => 0,
                    'revenue' => 0.0,
                ];
            }
            $staffMap[$staffKey]['units'] += $quantity;
            $staffMap[$staffKey]['revenue'] += $lineTotal;

            $day = $appointment?->starts_at?->toDateString();
            if ($day !== null) {
                $dailyRevenue[$day] = ($dailyRevenue[$day] ?? 0.0) + $lineTotal;
                $dailyUnits[$day] = ($dailyUnits[$day] ?? 0) + $quantity;
            }
        }

        $margin = $revenueWithCost - $cost;

        return [
            'range' => ['from' => $from, 'to' => $to],
            'units_sold' => $unitsSold,
            'transactions' => count($transactions),
            'gross_revenue' => round($revenue, 2),
            'cost_of_goods' => round($cost, 2),
            'gross_margin' => round($margin, 2),
            'margin_percent' => $revenueWithCost > 0 ? round(($margin / $revenueWithCost) * 100, 1) : null,
            'avg_line_value' => $lines->count() > 0 ? round($revenue / $lines->count(), 2) : 0.0,
            'counter_sale_revenue' => round($standaloneRevenue, 2),
            'attached_to_service_revenue' => round($attachedRevenue, 2),
            'top_products' => $this->sortedByRevenue($productMap, 10, function (array $row): array {
                $row['revenue'] = round($row['revenue'], 2);
                $row['cost'] = round($row['cost'], 2);
                $row['margin'] = round($row['revenue'] - $row['cost'], 2);

                return $row;
            }),
            'by_category' => $this->sortedByRevenue($categoryMap, 10),
            'by_staff' => $this->sortedByRevenue($staffMap, 10),
            'daily' => $this->buildDailySeries($from, $to, $dailyRevenue, $dailyUnits),
        ];
    }

    /**
     * @return Collection<int, AppointmentProduct>
     */
    private function lines(?int $saloonId, ?int $branchId, string $from, string $to): Collection
    {
        return AppointmentProduct::query()
            ->with(['product.category', 'staff', 'appointment'])
            ->whereHas('appointment', function (Builder $query) use ($saloonId, $branchId, $from, $to): void {
                $query
                    ->where('status', 'completed')
                    ->when($saloonId !== null, fn (Builder $inner) => $inner->where('saloon_id', $saloonId))
                    ->when($branchId !== null, fn (Builder $inner) => $inner->where('branch_id', $branchId))
                    ->whereDate('starts_at', '>=', $from)
                    ->whereDate('starts_at', '<=', $to);
            })
            ->get();
    }

    /**
     * @param  array<array-key, array<string, mixed>>  $map
     * @param  (callable(array<string, mixed>): array<string, mixed>)|null  $decorate
     * @return list<array<string, mixed>>
     */
    private function sortedByRevenue(array $map, int $limit, ?callable $decorate = null): array
    {
        $rows = array_values($map);
        usort($rows, fn (array $a, array $b): int => $b['revenue'] <=> $a['revenue']);

        return array_map(
            function (array $row) use ($decorate): array {
                $row = $decorate !== null ? $decorate($row) : $row;
                $row['revenue'] = round((float) $row['revenue'], 2);

                return $row;
            },
            array_slice($rows, 0, $limit),
        );
    }

    /**
     * @param  array<string, float>  $dailyRevenue
     * @param  array<string, int>  $dailyUnits
     * @return list<array{label: string, date: string, revenue: float, units: int}>
     */
    private function buildDailySeries(string $from, string $to, array $dailyRevenue, array $dailyUnits): array
    {
        $cursor = Carbon::parse($from)->startOfDay();
        $end = Carbon::parse($to)->startOfDay();
        $days = [];
        $guard = 0;

        while ($cursor->lte($end) && $guard < 62) {
            $key = $cursor->toDateString();

            $days[] = [
                'label' => $cursor->format('d M'),
                'date' => $key,
                'revenue' => round($dailyRevenue[$key] ?? 0.0, 2),
                'units' => $dailyUnits[$key] ?? 0,
            ];

            $cursor->addDay();
            $guard++;
        }

        return $days;
    }
}
