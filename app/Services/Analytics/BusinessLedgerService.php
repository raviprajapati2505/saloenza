<?php

namespace App\Services\Analytics;

use App\Models\Appointment;
use App\Models\Expense;
use App\Models\User;
use App\Support\Appointment\AppointmentPayment;
use Illuminate\Database\Eloquent\Builder;

/**
 * Day-close / hisab kitab for owners and managers: cash collected, staff work, expenses, net.
 */
class BusinessLedgerService
{
    /** Same work statuses used by staff earnings / worklog. */
    private const WORK_STATUSES = ['completed', 'in-progress'];

    /**
     * @return array<string, mixed>
     */
    public function summary(?int $saloonId, ?int $branchId, string $from, string $to): array
    {
        $appointments = Appointment::query()
            ->with(['services.service', 'services.staff', 'products.product', 'products.staff', 'staff', 'branch'])
            ->when($saloonId !== null, fn (Builder $query) => $query->where('saloon_id', $saloonId))
            ->when($branchId !== null, fn (Builder $query) => $query->where('branch_id', $branchId))
            ->whereDate('starts_at', '>=', $from)
            ->whereDate('starts_at', '<=', $to)
            ->orderBy('starts_at')
            ->get();

        $excluded = ['cancelled', 'no-show'];
        $collected = 0.0;
        $outstanding = 0.0;
        $booked = 0.0;
        $servicesRevenue = 0.0;
        $productsRevenue = 0.0;
        $completed = 0;
        $cancelled = 0;
        $minutesByStaff = [];
        $servicesByStaff = [];
        $revenueByStaff = [];
        $collectedByStaff = [];
        $paymentMethods = [];
        $daily = [];

        foreach ($appointments as $appointment) {
            $status = (string) $appointment->status;
            $grandTotal = (float) ($appointment->grand_total ?? $appointment->price ?? 0);
            $amountPaid = (float) ($appointment->amount_paid ?? 0);
            $day = $appointment->starts_at?->toDateString() ?? $from;

            if (! isset($daily[$day])) {
                $daily[$day] = [
                    'date' => $day,
                    'visits' => 0,
                    'completed' => 0,
                    'collected' => 0.0,
                    'outstanding' => 0.0,
                    'booked' => 0.0,
                ];
            }

            $daily[$day]['visits']++;
            $daily[$day]['booked'] += $grandTotal;
            $booked += $grandTotal;

            if (in_array($status, $excluded, true)) {
                $cancelled++;

                continue;
            }

            $due = AppointmentPayment::balanceDue($grandTotal, $amountPaid);
            $collected += $amountPaid;
            $outstanding += $due;
            $daily[$day]['collected'] += $amountPaid;
            $daily[$day]['outstanding'] += $due;

            if ($status === 'completed') {
                $completed++;
                $daily[$day]['completed']++;
            }

            if ($amountPaid > 0 && $appointment->payment_method) {
                $method = (string) $appointment->payment_method;
                $paymentMethods[$method] = ($paymentMethods[$method] ?? 0.0) + $amountPaid;
            }

            $serviceTotal = (float) ($appointment->services_total ?? 0);
            $productTotal = (float) ($appointment->products_total ?? 0);
            if ($serviceTotal === 0.0 && $productTotal === 0.0) {
                $serviceTotal = (float) ($appointment->price ?? 0);
            }
            if (in_array($status, self::WORK_STATUSES, true)) {
                $servicesRevenue += $serviceTotal;
                $productsRevenue += $productTotal;
                $this->accumulateStaff(
                    $appointment,
                    $grandTotal,
                    $amountPaid,
                    $minutesByStaff,
                    $servicesByStaff,
                    $revenueByStaff,
                    $collectedByStaff,
                );
            }
        }

        $expenses = $this->expenses($saloonId, $branchId, $from, $to);
        $staffRows = $this->staffRows(
            $saloonId,
            $branchId,
            $minutesByStaff,
            $servicesByStaff,
            $revenueByStaff,
            $collectedByStaff,
        );

        $commissionTotal = array_sum(array_column($staffRows, 'commission'));
        $netCollected = round($collected - $expenses['total'], 2);

        ksort($daily);

        return [
            'range' => ['from' => $from, 'to' => $to],
            'summary' => [
                'visits' => $appointments->count(),
                'completed' => $completed,
                'cancelled' => $cancelled,
                'booked_revenue' => round($booked, 2),
                'collected' => round($collected, 2),
                'outstanding' => round($outstanding, 2),
                'services_revenue' => round($servicesRevenue, 2),
                'products_revenue' => round($productsRevenue, 2),
                'expenses' => $expenses['total'],
                'staff_commission' => round($commissionTotal, 2),
                'net_collected' => $netCollected,
            ],
            'payment_methods' => collect($paymentMethods)
                ->map(fn ($amount, $method) => ['method' => $method, 'amount' => round($amount, 2)])
                ->sortByDesc('amount')
                ->values()
                ->all(),
            'expenses' => $expenses,
            'staff' => $staffRows,
            'daily' => array_values(array_map(static function (array $row): array {
                $row['collected'] = round($row['collected'], 2);
                $row['outstanding'] = round($row['outstanding'], 2);
                $row['booked'] = round($row['booked'], 2);

                return $row;
            }, $daily)),
        ];
    }

    /**
     * @param  array<int, int>  $minutesByStaff
     * @param  array<int, int>  $servicesByStaff
     * @param  array<int, float>  $revenueByStaff
     * @param  array<int, float>  $collectedByStaff
     */
    private function accumulateStaff(
        Appointment $appointment,
        float $grandTotal,
        float $amountPaid,
        array &$minutesByStaff,
        array &$servicesByStaff,
        array &$revenueByStaff,
        array &$collectedByStaff,
    ): void {
        $divisor = max($grandTotal, 0.01);
        $attributed = false;

        if ($appointment->services->isNotEmpty()) {
            foreach ($appointment->services as $line) {
                $staffId = (int) ($line->staff_id ?? 0);
                if ($staffId <= 0) {
                    continue;
                }

                $revenue = (float) $line->price * max(1, (int) ($line->quantity ?? 1));
                $minutesByStaff[$staffId] = ($minutesByStaff[$staffId] ?? 0) + (int) ($line->duration_minutes ?? 0);
                $servicesByStaff[$staffId] = ($servicesByStaff[$staffId] ?? 0) + 1;
                $revenueByStaff[$staffId] = ($revenueByStaff[$staffId] ?? 0.0) + $revenue;
                $collectedByStaff[$staffId] = ($collectedByStaff[$staffId] ?? 0.0) + (($revenue / $divisor) * $amountPaid);
                $attributed = true;
            }
        }

        if ($appointment->products->isNotEmpty()) {
            foreach ($appointment->products as $line) {
                $staffId = (int) ($line->staff_id ?? 0);
                if ($staffId <= 0) {
                    continue;
                }

                $revenue = (float) ($line->line_total ?? 0);
                $servicesByStaff[$staffId] = ($servicesByStaff[$staffId] ?? 0) + 1;
                $revenueByStaff[$staffId] = ($revenueByStaff[$staffId] ?? 0.0) + $revenue;
                $collectedByStaff[$staffId] = ($collectedByStaff[$staffId] ?? 0.0) + (($revenue / $divisor) * $amountPaid);
                $attributed = true;
            }
        }

        if ($attributed) {
            return;
        }

        $staffId = (int) ($appointment->staff_id ?? 0);
        if ($staffId <= 0) {
            return;
        }

        $minutes = $appointment->starts_at && $appointment->ends_at
            ? max(0, (int) $appointment->starts_at->diffInMinutes($appointment->ends_at))
            : 0;
        $minutesByStaff[$staffId] = ($minutesByStaff[$staffId] ?? 0) + $minutes;
        $servicesByStaff[$staffId] = ($servicesByStaff[$staffId] ?? 0) + 1;
        $revenueByStaff[$staffId] = ($revenueByStaff[$staffId] ?? 0.0) + $grandTotal;
        $collectedByStaff[$staffId] = ($collectedByStaff[$staffId] ?? 0.0) + $amountPaid;
    }

    /**
     * @param  array<int, int>  $minutesByStaff
     * @param  array<int, int>  $servicesByStaff
     * @param  array<int, float>  $revenueByStaff
     * @param  array<int, float>  $collectedByStaff
     * @return list<array<string, mixed>>
     */
    private function staffRows(
        ?int $saloonId,
        ?int $branchId,
        array $minutesByStaff,
        array $servicesByStaff,
        array $revenueByStaff,
        array $collectedByStaff,
    ): array {
        $ids = array_values(array_unique(array_merge(
            array_keys($minutesByStaff),
            array_keys($servicesByStaff),
            array_keys($revenueByStaff),
        )));

        if ($ids === []) {
            return [];
        }

        $members = User::query()
            ->with('role:id,name,code')
            ->when($saloonId !== null, fn (Builder $query) => $query->where('saloon_id', $saloonId))
            ->when($branchId !== null, fn (Builder $query) => $query->where('branch_id', $branchId))
            ->whereIn('id', $ids)
            ->get(['id', 'name', 'commission_rate', 'role_id', 'branch_id']);

        $rows = [];

        foreach ($members as $member) {
            $id = (int) $member->id;
            $revenue = round($revenueByStaff[$id] ?? 0.0, 2);
            $rate = (float) ($member->commission_rate ?? 0);
            $minutes = (int) ($minutesByStaff[$id] ?? 0);

            $rows[] = [
                'staff_id' => $id,
                'name' => $member->name,
                'role' => $member->role?->name,
                'services' => (int) ($servicesByStaff[$id] ?? 0),
                'minutes_worked' => $minutes,
                'hours_worked' => round($minutes / 60, 1),
                'revenue_generated' => $revenue,
                'collected' => round($collectedByStaff[$id] ?? 0.0, 2),
                'commission_rate' => $rate,
                'commission' => round($revenue * ($rate / 100), 2),
            ];
        }

        usort($rows, fn (array $a, array $b): int => $b['revenue_generated'] <=> $a['revenue_generated']);

        return $rows;
    }

    /**
     * @return array{total: float, by_category: list<array{category: string, amount: float}>}
     */
    private function expenses(?int $saloonId, ?int $branchId, string $from, string $to): array
    {
        $rows = Expense::query()
            ->when($saloonId !== null, fn (Builder $query) => $query->where('saloon_id', $saloonId))
            ->when($branchId !== null, fn (Builder $query) => $query->where('branch_id', $branchId))
            ->incurredBetween($from, $to)
            ->get(['category', 'amount']);

        $total = 0.0;
        $byCategory = [];

        foreach ($rows as $row) {
            $amount = (float) $row->amount;
            $total += $amount;
            $category = (string) $row->category;
            $byCategory[$category] = ($byCategory[$category] ?? 0.0) + $amount;
        }

        $categories = [];
        foreach ($byCategory as $category => $amount) {
            $categories[] = [
                'category' => $category,
                'amount' => round($amount, 2),
            ];
        }
        usort($categories, fn (array $a, array $b): int => $b['amount'] <=> $a['amount']);

        return [
            'total' => round($total, 2),
            'by_category' => $categories,
        ];
    }
}
