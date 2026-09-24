<?php

namespace App\Services\Staff;

use App\Models\Appointment;
use App\Models\User;
use App\Services\Commission\CommissionRuleResolver;
use App\Support\Customer\CustomerContactPayload;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class StaffEarningsService
{
    private const EARNINGS_STATUSES = ['completed', 'in-progress'];

    public function __construct(
        private readonly CommissionRuleResolver $commissionResolver,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function report(User $staff, Carbon $from, Carbon $to, ?User $viewer = null): array
    {
        $staffId = (int) $staff->id;
        $commissionRate = (float) ($staff->commission_rate ?? 0);
        $viewer ??= $staff;

        $appointments = $this->baseQuery($staff, $from, $to)
            ->with([
                'customer',
                'branch',
                'service.category',
                'services.service.category',
                'services.staff',
                'products.product.category',
            ])
            ->orderBy('starts_at')
            ->get();

        $lines = [];
        $days = [];
        $totalRevenue = 0.0;
        $totalCommission = 0.0;
        $totalCollected = 0.0;
        $totalMinutes = 0;
        $completedCount = 0;
        $serviceCount = 0;

        foreach ($appointments as $appointment) {
            $myLines = $this->staffLines($appointment, $staffId);

            if ($myLines === []) {
                continue;
            }

            if ($appointment->status === 'completed') {
                $completedCount++;
            }

            $dayKey = $appointment->starts_at?->toDateString() ?? 'unknown';
            if (! isset($days[$dayKey])) {
                $days[$dayKey] = [
                    'date' => $dayKey,
                    'services' => 0,
                    'minutes_worked' => 0,
                    'revenue' => 0.0,
                    'commission' => 0.0,
                    'collected' => 0.0,
                ];
            }

            $grandTotal = max((float) ($appointment->grand_total ?? $appointment->price ?? 0), 0.01);
            $paid = (float) ($appointment->amount_paid ?? 0);

            foreach ($myLines as $line) {
                $revenue = round((float) $line['revenue'], 2);
                $resolved = $this->commissionResolver->resolve($staff, [
                    'applies_to' => $line['kind'] === 'product' ? 'product' : 'service',
                    'service_id' => $line['service_id'] ?? null,
                    'product_id' => $line['product_id'] ?? null,
                    'category_id' => $line['category_id'] ?? null,
                    'revenue' => $revenue,
                    'duration_minutes' => $line['duration_minutes'] ?? null,
                    'discount_allocated' => $line['discount_allocated'] ?? 0,
                    'branch_id' => $appointment->branch_id,
                    'earned_on' => $appointment->starts_at,
                ]);

                $commission = round((float) $resolved['commission'], 2);
                $lineRate = (float) $resolved['rate'];
                $minutes = (int) ($line['duration_minutes'] ?? 0);
                $collectedShare = round(($revenue / $grandTotal) * $paid, 2);
                $totalRevenue += $revenue;
                $totalCommission += $commission;
                $totalCollected += $collectedShare;
                $totalMinutes += $minutes;
                $serviceCount++;

                $days[$dayKey]['services']++;
                $days[$dayKey]['minutes_worked'] += $minutes;
                $days[$dayKey]['revenue'] += $revenue;
                $days[$dayKey]['commission'] += $commission;
                $days[$dayKey]['collected'] += $collectedShare;

                $identity = CustomerContactPayload::identity($appointment->customer, $viewer);

                $lines[] = [
                    'appointment_id' => $appointment->id,
                    'date' => $appointment->starts_at?->toDateString(),
                    'starts_at' => $appointment->starts_at?->toISOString(),
                    'ends_at' => $appointment->ends_at?->toISOString(),
                    'status' => $appointment->status,
                    'type' => $appointment->type,
                    'kind' => $line['kind'],
                    'booking_source' => $appointment->booking_source,
                    'service_name' => $line['service_name'],
                    'duration_minutes' => $minutes > 0 ? $minutes : null,
                    'revenue' => $revenue,
                    'collected' => $collectedShare,
                    'commission_rate' => $lineRate,
                    'commission' => $commission,
                    'rule_name' => $resolved['rule_name'],
                    'calc_type' => $resolved['calc_type'],
                    'payment_method' => $appointment->payment_method,
                    'branch' => $appointment->branch ? [
                        'id' => $appointment->branch->id,
                        'name' => $appointment->branch->branch_name,
                    ] : null,
                    'customer' => [
                        'id' => $identity['id'],
                        'name' => $identity['name'] ?? 'Walk-in',
                        'phone' => $identity['phone'],
                    ],
                ];
            }
        }

        ksort($days);

        return [
            'staff' => [
                'id' => $staffId,
                'name' => $staff->name,
                'commission_rate' => $commissionRate,
                'per_month_salary' => $staff->per_month_salary !== null
                    ? (float) $staff->per_month_salary
                    : null,
            ],
            'period' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ],
            'summary' => [
                'appointments_completed' => $completedCount,
                'services_performed' => $serviceCount,
                'minutes_worked' => $totalMinutes,
                'hours_worked' => round($totalMinutes / 60, 1),
                'revenue_generated' => round($totalRevenue, 2),
                'collected' => round($totalCollected, 2),
                'commission_earned' => round($totalCommission, 2),
            ],
            'days' => array_values(array_map(static function (array $day): array {
                $day['revenue'] = round($day['revenue'], 2);
                $day['commission'] = round($day['commission'], 2);
                $day['collected'] = round($day['collected'], 2);
                $day['hours_worked'] = round($day['minutes_worked'] / 60, 1);

                return $day;
            }, $days)),
            'lines' => $lines,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function presets(User $staff, ?User $viewer = null): array
    {
        $today = now()->startOfDay();
        $monthStart = now()->copy()->startOfMonth();
        $prevMonthStart = now()->copy()->subMonth()->startOfMonth();
        $prevMonthEnd = now()->copy()->subMonth()->endOfMonth();

        return [
            'today' => $this->report($staff, $today, now()->endOfDay(), $viewer),
            'current_month' => $this->report($staff, $monthStart, now()->endOfDay(), $viewer),
            'previous_month' => $this->report($staff, $prevMonthStart, $prevMonthEnd, $viewer),
        ];
    }

    private function baseQuery(User $staff, Carbon $from, Carbon $to): Builder
    {
        $staffId = (int) $staff->id;

        return Appointment::query()
            ->where('saloon_id', $staff->saloon_id)
            ->when($staff->branch_id, fn (Builder $query) => $query->where('branch_id', $staff->branch_id))
            ->whereIn('status', self::EARNINGS_STATUSES)
            ->whereDate('starts_at', '>=', $from->toDateString())
            ->whereDate('starts_at', '<=', $to->toDateString())
            ->where(function (Builder $query) use ($staffId): void {
                $query->where('staff_id', $staffId)
                    ->orWhereHas('services', fn (Builder $inner) => $inner->where('staff_id', $staffId))
                    ->orWhereHas('products', fn (Builder $inner) => $inner->where('staff_id', $staffId));
            });
    }

    /**
     * @return list<array{
     *     service_name: string,
     *     revenue: float,
     *     duration_minutes: int|null,
     *     kind: string,
     *     service_id: int|null,
     *     product_id: int|null,
     *     category_id: int|null,
     *     discount_allocated: float
     * }>
     */
    private function staffLines(Appointment $appointment, int $staffId): array
    {
        $lines = [];
        $appointmentDiscount = max((float) ($appointment->discount ?? 0), 0);

        if ($appointment->relationLoaded('services') && $appointment->services->isNotEmpty()) {
            foreach ($appointment->services as $line) {
                if ((int) ($line->staff_id ?? 0) !== $staffId) {
                    continue;
                }

                $lines[] = [
                    'kind' => 'service',
                    'service_name' => $line->service?->name ?? 'Service',
                    'revenue' => (float) $line->price * max(1, (int) ($line->quantity ?? 1)),
                    'duration_minutes' => $line->duration_minutes,
                    'service_id' => $line->service_id !== null ? (int) $line->service_id : null,
                    'product_id' => null,
                    'category_id' => $line->service?->category_id !== null
                        ? (int) $line->service->category_id
                        : null,
                    'discount_allocated' => 0.0,
                ];
            }
        }

        if ($appointment->relationLoaded('products') && $appointment->products->isNotEmpty()) {
            foreach ($appointment->products as $line) {
                if ((int) ($line->staff_id ?? 0) !== $staffId) {
                    continue;
                }

                $lines[] = [
                    'kind' => 'product',
                    'service_name' => $line->product?->name ?? 'Product',
                    'revenue' => (float) ($line->line_total ?? 0),
                    'duration_minutes' => null,
                    'service_id' => null,
                    'product_id' => $line->product_id !== null ? (int) $line->product_id : null,
                    'category_id' => $line->product?->category_id !== null
                        ? (int) $line->product->category_id
                        : null,
                    'discount_allocated' => 0.0,
                ];
            }
        }

        if ($lines === [] && (int) ($appointment->staff_id ?? 0) === $staffId) {
            $lines[] = [
                'kind' => 'service',
                'service_name' => $appointment->service?->name ?? 'Service',
                'revenue' => (float) ($appointment->grand_total ?? $appointment->price ?? 0),
                'duration_minutes' => $appointment->starts_at && $appointment->ends_at
                    ? max(1, (int) $appointment->starts_at->diffInMinutes($appointment->ends_at))
                    : null,
                'service_id' => $appointment->service_id !== null ? (int) $appointment->service_id : null,
                'product_id' => null,
                'category_id' => $appointment->service?->category_id !== null
                    ? (int) $appointment->service->category_id
                    : null,
                'discount_allocated' => $appointmentDiscount,
            ];

            return $lines;
        }

        if ($lines === []) {
            return [];
        }

        $lineRevenueSum = array_sum(array_column($lines, 'revenue'));
        if ($appointmentDiscount > 0 && $lineRevenueSum > 0) {
            foreach ($lines as $index => $line) {
                $share = ((float) $line['revenue'] / $lineRevenueSum) * $appointmentDiscount;
                $lines[$index]['discount_allocated'] = round($share, 2);
            }
        }

        return $lines;
    }
}
