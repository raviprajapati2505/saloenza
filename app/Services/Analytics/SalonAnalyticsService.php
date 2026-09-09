<?php

namespace App\Services\Analytics;

use App\Models\Appointment;
use App\Support\Appointment\AppointmentPayment;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class SalonAnalyticsService
{
    /**
     * @return array<string, mixed>
     */
    public function summary(?int $saloonId, ?int $branchId, string $from, string $to): array
    {
        $query = $this->baseQuery($saloonId, $branchId, $from, $to);
        $appointments = (clone $query)
            ->with(['services.service'])
            ->get();

        $completedStatuses = ['completed'];
        $excludedStatuses = ['cancelled', 'no-show'];

        $collected = 0.0;
        $outstanding = 0.0;
        $bookedTotal = 0.0;
        $billableVisits = 0;
        $statusCounts = [];
        $paymentMethods = [];
        $dailyCollected = [];
        $serviceMap = [];

        foreach ($appointments as $appointment) {
            $status = (string) $appointment->status;
            $statusCounts[$status] = ($statusCounts[$status] ?? 0) + 1;

            $grandTotal = (float) ($appointment->grand_total ?? 0);
            $amountPaid = (float) ($appointment->amount_paid ?? 0);
            $bookedTotal += $grandTotal;

            if (! in_array($status, $excludedStatuses, true)) {
                $billableVisits++;
                $collected += $amountPaid;
                $outstanding += AppointmentPayment::balanceDue($grandTotal, $amountPaid);

                if ($amountPaid > 0 && $appointment->payment_method) {
                    $method = (string) $appointment->payment_method;
                    $paymentMethods[$method] = ($paymentMethods[$method] ?? 0) + $amountPaid;
                }

                $day = $appointment->starts_at?->toDateString();
                if ($day && $amountPaid > 0) {
                    $dailyCollected[$day] = ($dailyCollected[$day] ?? 0) + $amountPaid;
                }
            }

            if (in_array($status, $completedStatuses, true)) {
                $lines = $appointment->services->isNotEmpty()
                    ? $appointment->services
                    : collect([$appointment]);

                foreach ($lines as $line) {
                    $service = $line->relationLoaded('service') ? $line->service : null;
                    $name = $service?->name ?? 'Service';
                    $key = (string) ($line->service_id ?? $name);
                    $linePaidShare = $appointment->services->isNotEmpty()
                        ? ((float) $line->price / max((float) $appointment->price, 1)) * $amountPaid
                        : $amountPaid;

                    if (! isset($serviceMap[$key])) {
                        $serviceMap[$key] = ['name' => $name, 'bookings' => 0, 'revenue' => 0.0];
                    }
                    $serviceMap[$key]['bookings'] += 1;
                    $serviceMap[$key]['revenue'] += $linePaidShare;
                }
            }
        }

        $days = $this->buildDailySeries($from, $to, $dailyCollected, $appointments);

        uasort($serviceMap, fn ($a, $b) => $b['revenue'] <=> $a['revenue']);

        return [
            'range' => ['from' => $from, 'to' => $to],
            'appointments_count' => $appointments->count(),
            'completed_count' => $statusCounts['completed'] ?? 0,
            'cancelled_count' => ($statusCounts['cancelled'] ?? 0) + ($statusCounts['no-show'] ?? 0),
            'collected_revenue' => round($collected, 2),
            'outstanding_revenue' => round($outstanding, 2),
            'booked_revenue' => round($bookedTotal, 2),
            'avg_collected_ticket' => $billableVisits > 0
                ? round($collected / $billableVisits, 2)
                : 0,
            'payment_status_counts' => $this->paymentStatusCounts($appointments),
            'payment_methods' => collect($paymentMethods)
                ->map(fn ($amount, $method) => ['method' => $method, 'amount' => round($amount, 2)])
                ->sortByDesc('amount')
                ->values()
                ->all(),
            'status_counts' => $statusCounts,
            'daily_collected' => $days,
            'top_services' => array_slice(array_values($serviceMap), 0, 8),
        ];
    }

    /**
     * @param  array<string, float>  $dailyCollected
     * @return list<array{label: string, date: string, amount: float, appointments: int}>
     */
    private function buildDailySeries(string $from, string $to, array $dailyCollected, $appointments): array
    {
        $start = Carbon::parse($from)->startOfDay();
        $end = Carbon::parse($to)->startOfDay();
        $days = [];
        $cursor = $start->copy();
        $guard = 0;

        while ($cursor->lte($end) && $guard < 62) {
            $key = $cursor->toDateString();
            $dayAppointments = $appointments->filter(
                fn (Appointment $row) => $row->starts_at?->toDateString() === $key,
            );

            $days[] = [
                'label' => $cursor->format('d M'),
                'date' => $key,
                'amount' => round($dailyCollected[$key] ?? 0, 2),
                'appointments' => $dayAppointments->count(),
            ];

            $cursor->addDay();
            $guard++;
        }

        return $days;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Appointment>  $appointments
     * @return array<string, int>
     */
    private function paymentStatusCounts($appointments): array
    {
        $counts = [];
        foreach ($appointments as $appointment) {
            $status = (string) ($appointment->payment_status ?? AppointmentPayment::STATUS_UNPAID);
            $counts[$status] = ($counts[$status] ?? 0) + 1;
        }

        return $counts;
    }

    private function baseQuery(?int $saloonId, ?int $branchId, string $from, string $to): Builder
    {
        return Appointment::query()
            ->when($saloonId !== null, fn (Builder $query) => $query->where('saloon_id', $saloonId))
            ->when($branchId !== null, fn (Builder $query) => $query->where('branch_id', $branchId))
            ->whereDate('starts_at', '>=', $from)
            ->whereDate('starts_at', '<=', $to);
    }
}
