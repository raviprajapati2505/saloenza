<?php

namespace App\Services\Analytics;

use App\Models\Appointment;
use App\Models\BranchProductStock;
use App\Models\Expense;
use App\Models\User;
use App\Support\Expense\ExpenseCategory;
use App\Support\Role\RoleCodes;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Profit and loss for a salon over a date range.
 *
 * Revenue is recognised on completed visits (accrual), which is the same point
 * at which stock leaves the branch, so revenue and cost of goods always line up.
 */
class ProfitReportService
{
    private const DAYS_PER_MONTH = 30;

    /**
     * @return array<string, mixed>
     */
    public function summary(?int $saloonId, ?int $branchId, string $from, string $to): array
    {
        $appointments = $this->completedAppointments($saloonId, $branchId, $from, $to);
        $costLookup = $this->branchCostLookup($appointments);

        $revenue = $this->revenue($appointments);
        $cogs = $this->costOfGoods($appointments, $costLookup);
        $grossProfit = round($revenue['net'] - $cogs['total'], 2);

        $expenses = $this->operatingExpenses($saloonId, $branchId, $from, $to);
        $staff = $this->staffCost($saloonId, $branchId, $from, $to, $appointments, $expenses['has_salary_rows']);

        $netProfit = round($grossProfit - $staff['total'] - $expenses['total'], 2);

        return [
            'range' => ['from' => $from, 'to' => $to],
            'revenue' => $revenue,
            'cost_of_goods' => $cogs,
            'gross_profit' => $grossProfit,
            'gross_margin_percent' => $revenue['net'] > 0
                ? round(($grossProfit / $revenue['net']) * 100, 1)
                : null,
            'staff_cost' => $staff,
            'operating_expenses' => $expenses,
            'net_profit' => $netProfit,
            'net_margin_percent' => $revenue['net'] > 0
                ? round(($netProfit / $revenue['net']) * 100, 1)
                : null,
            'daily' => $this->dailySeries(
                $from,
                $to,
                $appointments,
                $costLookup,
                $expenses['daily'],
                $staff['total'],
            ),
        ];
    }

    /**
     * @return Collection<int, Appointment>
     */
    private function completedAppointments(?int $saloonId, ?int $branchId, string $from, string $to): Collection
    {
        return Appointment::query()
            ->with(['services', 'products'])
            ->where('status', 'completed')
            ->when($saloonId !== null, fn (Builder $query) => $query->where('saloon_id', $saloonId))
            ->when($branchId !== null, fn (Builder $query) => $query->where('branch_id', $branchId))
            ->whereDate('starts_at', '>=', $from)
            ->whereDate('starts_at', '<=', $to)
            ->get();
    }

    /**
     * Branch cost prices for every product touched in the range, keyed "branch:product".
     *
     * @param  Collection<int, Appointment>  $appointments
     * @return array<string, float>
     */
    private function branchCostLookup(Collection $appointments): array
    {
        $pairs = [];

        foreach ($appointments as $appointment) {
            if ($appointment->branch_id === null) {
                continue;
            }

            foreach ($appointment->services as $line) {
                if ($line->product_id !== null) {
                    $pairs[(int) $appointment->branch_id][(int) $line->product_id] = true;
                }
            }
        }

        $lookup = [];

        foreach ($pairs as $branchId => $productIds) {
            $rows = BranchProductStock::query()
                ->where('branch_id', $branchId)
                ->whereIn('product_id', array_keys($productIds))
                ->get(['product_id', 'cost_price']);

            foreach ($rows as $row) {
                if ($row->cost_price === null) {
                    continue;
                }

                $lookup[$branchId.':'.$row->product_id] = (float) $row->cost_price;
            }
        }

        return $lookup;
    }

    /**
     * @param  Collection<int, Appointment>  $appointments
     * @return array<string, float>
     */
    private function revenue(Collection $appointments): array
    {
        $services = 0.0;
        $products = 0.0;
        $discounts = 0.0;
        $net = 0.0;

        foreach ($appointments as $appointment) {
            $serviceTotal = (float) ($appointment->services_total ?? 0);
            $productTotal = (float) ($appointment->products_total ?? 0);

            // Visits booked before line-level totals existed only carry `price`.
            if ($serviceTotal === 0.0 && $productTotal === 0.0) {
                $serviceTotal = (float) ($appointment->price ?? 0);
            }

            $services += $serviceTotal;
            $products += $productTotal;
            $discounts += (float) ($appointment->discount ?? 0);
            $net += (float) ($appointment->grand_total ?? $appointment->price ?? 0);
        }

        return [
            'services' => round($services, 2),
            'products' => round($products, 2),
            'gross' => round($services + $products, 2),
            'discounts' => round($discounts, 2),
            'net' => round($net, 2),
        ];
    }

    /**
     * @param  Collection<int, Appointment>  $appointments
     * @param  array<string, float>  $costLookup
     * @return array<string, mixed>
     */
    private function costOfGoods(Collection $appointments, array $costLookup): array
    {
        $retail = 0.0;
        $consumables = 0.0;
        $uncosted = 0;

        foreach ($appointments as $appointment) {
            foreach ($appointment->products as $line) {
                if ($line->unit_cost === null) {
                    $uncosted++;

                    continue;
                }

                $retail += (float) $line->unit_cost * max(1, (int) $line->quantity);
            }

            foreach ($appointment->services as $line) {
                if ($line->product_id === null || $appointment->branch_id === null) {
                    continue;
                }

                $key = $appointment->branch_id.':'.$line->product_id;
                if (! isset($costLookup[$key])) {
                    $uncosted++;

                    continue;
                }

                $consumables += $costLookup[$key] * max(1, (int) $line->quantity);
            }
        }

        return [
            'retail_products' => round($retail, 2),
            'service_consumables' => round($consumables, 2),
            'total' => round($retail + $consumables, 2),
            'uncosted_lines' => $uncosted,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function operatingExpenses(?int $saloonId, ?int $branchId, string $from, string $to): array
    {
        $rows = Expense::query()
            ->when($saloonId !== null, fn (Builder $query) => $query->where('saloon_id', $saloonId))
            // A branch P&L counts only its own spend; salon-wide overheads are
            // reported separately because there is no allocation rule to split them.
            ->when($branchId !== null, fn (Builder $query) => $query->where('branch_id', $branchId))
            ->incurredBetween($from, $to)
            ->get(['category', 'amount', 'incurred_on', 'branch_id']);

        $total = 0.0;
        $byCategory = [];
        $daily = [];
        $hasSalaryRows = false;

        foreach ($rows as $row) {
            $amount = (float) $row->amount;
            $category = (string) $row->category;
            $total += $amount;

            if (ExpenseCategory::overlapsPayroll($category)) {
                $hasSalaryRows = true;
            }

            $byCategory[$category] = ($byCategory[$category] ?? 0.0) + $amount;

            $day = $row->incurred_on?->toDateString();
            if ($day !== null) {
                $daily[$day] = ($daily[$day] ?? 0.0) + $amount;
            }
        }

        $unallocated = 0.0;
        if ($branchId !== null) {
            $unallocated = (float) Expense::query()
                ->when($saloonId !== null, fn (Builder $query) => $query->where('saloon_id', $saloonId))
                ->whereNull('branch_id')
                ->incurredBetween($from, $to)
                ->sum('amount');
        }

        $categories = [];
        foreach ($byCategory as $category => $amount) {
            $categories[] = [
                'category' => $category,
                'label' => ExpenseCategory::label($category),
                'amount' => round($amount, 2),
            ];
        }
        usort($categories, fn (array $a, array $b): int => $b['amount'] <=> $a['amount']);

        return [
            'total' => round($total, 2),
            'by_category' => $categories,
            'unallocated_overheads' => round($unallocated, 2),
            'has_salary_rows' => $hasSalaryRows,
            'daily' => $daily,
        ];
    }

    /**
     * @param  Collection<int, Appointment>  $appointments
     * @return array<string, mixed>
     */
    private function staffCost(
        ?int $saloonId,
        ?int $branchId,
        string $from,
        string $to,
        Collection $appointments,
        bool $salariesRecordedAsExpenses,
    ): array {
        $revenueByStaff = [];

        foreach ($appointments as $appointment) {
            foreach ($appointment->services as $line) {
                if ($line->staff_id === null) {
                    continue;
                }

                $revenueByStaff[(int) $line->staff_id] = ($revenueByStaff[(int) $line->staff_id] ?? 0.0)
                    + (float) $line->price * max(1, (int) $line->quantity);
            }

            foreach ($appointment->products as $line) {
                if ($line->staff_id === null) {
                    continue;
                }

                $revenueByStaff[(int) $line->staff_id] = ($revenueByStaff[(int) $line->staff_id] ?? 0.0)
                    + (float) $line->line_total;
            }
        }

        $rangeDays = $this->rangeDays($from, $to);
        $rangeStart = Carbon::parse($from)->startOfDay();

        $members = User::query()
            ->when($saloonId !== null, fn (Builder $query) => $query->where('saloon_id', $saloonId))
            ->when($branchId !== null, fn (Builder $query) => $query->where('branch_id', $branchId))
            ->whereHas('role', fn (Builder $query) => $query->whereIn('code', [
                RoleCodes::SALON_STAFF,
                RoleCodes::SALON_BRANCH_MANAGER,
            ]))
            ->get(['id', 'name', 'commission_rate', 'per_month_salary', 'joined_at', 'is_active']);

        $commissionTotal = 0.0;
        $salaryTotal = 0.0;
        $rows = [];

        foreach ($members as $member) {
            $generated = $revenueByStaff[(int) $member->id] ?? 0.0;
            $rate = (float) ($member->commission_rate ?? 0);
            $commission = round($generated * ($rate / 100), 2);

            $salary = $salariesRecordedAsExpenses
                ? 0.0
                : $this->proratedSalary($member, $rangeStart, $rangeDays);

            if ($generated === 0.0 && $commission === 0.0 && $salary === 0.0) {
                continue;
            }

            $commissionTotal += $commission;
            $salaryTotal += $salary;

            $rows[] = [
                'staff_id' => (int) $member->id,
                'name' => $member->name,
                'revenue_generated' => round($generated, 2),
                'commission_rate' => $rate,
                'commission' => $commission,
                'salary' => round($salary, 2),
                'total_cost' => round($commission + $salary, 2),
            ];
        }

        usort($rows, fn (array $a, array $b): int => $b['total_cost'] <=> $a['total_cost']);

        return [
            'commission' => round($commissionTotal, 2),
            'salaries' => round($salaryTotal, 2),
            'total' => round($commissionTotal + $salaryTotal, 2),
            'salaries_from_expenses' => $salariesRecordedAsExpenses,
            'by_staff' => $rows,
        ];
    }

    private function proratedSalary(User $member, Carbon $rangeStart, int $rangeDays): float
    {
        $monthly = (float) ($member->per_month_salary ?? 0);

        if ($monthly <= 0 || ! $member->is_active) {
            return 0.0;
        }

        $payableDays = $rangeDays;

        if ($member->joined_at !== null) {
            $joined = Carbon::parse($member->joined_at)->startOfDay();

            if ($joined->gt($rangeStart)) {
                $payableDays = max(0, $rangeDays - (int) $rangeStart->diffInDays($joined));
            }
        }

        return round(($monthly / self::DAYS_PER_MONTH) * $payableDays, 2);
    }

    /**
     * @param  Collection<int, Appointment>  $appointments
     * @param  array<string, float>  $costLookup
     * @param  array<string, float>  $dailyExpenses
     * @return list<array<string, mixed>>
     */
    private function dailySeries(
        string $from,
        string $to,
        Collection $appointments,
        array $costLookup,
        array $dailyExpenses,
        float $staffCostTotal,
    ): array {
        $grouped = $appointments->groupBy(fn (Appointment $row): string => (string) $row->starts_at?->toDateString());

        $cursor = Carbon::parse($from)->startOfDay();
        $end = Carbon::parse($to)->startOfDay();
        $days = [];
        $guard = 0;
        $rangeDays = max(1, $this->rangeDays($from, $to));
        // Payroll has no per-day timestamp, so it is smeared evenly across the range.
        $staffPerDay = round($staffCostTotal / $rangeDays, 2);

        while ($cursor->lte($end) && $guard < 62) {
            $key = $cursor->toDateString();
            $dayAppointments = $grouped->get($key, collect());

            $revenue = $this->revenue($dayAppointments)['net'];
            $cogs = $this->costOfGoods($dayAppointments, $costLookup)['total'];
            $expenses = round($dailyExpenses[$key] ?? 0.0, 2);
            $grossProfit = round($revenue - $cogs, 2);

            $days[] = [
                'label' => $cursor->format('d M'),
                'date' => $key,
                'revenue' => $revenue,
                'cogs' => $cogs,
                'gross_profit' => $grossProfit,
                'expenses' => round($expenses + $staffPerDay, 2),
                'net_profit' => round($grossProfit - $expenses - $staffPerDay, 2),
            ];

            $cursor->addDay();
            $guard++;
        }

        return $days;
    }

    private function rangeDays(string $from, string $to): int
    {
        $start = Carbon::parse($from)->startOfDay();
        $end = Carbon::parse($to)->startOfDay();

        return (int) $start->diffInDays($end) + 1;
    }
}
