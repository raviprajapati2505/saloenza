<?php

namespace App\Services\Analytics;

use App\Models\Appointment;
use App\Models\User;
use App\Support\Appointment\AppointmentPayment;
use App\Support\Customer\CustomerContactAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class OutstandingPaymentReportService
{
    /**
     * @return array<string, mixed>
     */
    public function summary(?int $saloonId, ?int $branchId, string $from, string $to, int $overdueDays = 0, ?User $viewer = null): array
    {
        // Open balances are as-of `to`. Paid counts stay inside the selected range.
        $appointments = $this->bills($saloonId, $branchId, $to);

        $paidCount = 0;
        $pendingCount = 0;
        $collected = 0.0;
        $outstanding = 0.0;
        $customers = [];
        $bills = [];

        foreach ($appointments as $appointment) {
            if (in_array($appointment->status, ['cancelled', 'no-show'], true)) {
                continue;
            }

            $grandTotal = (float) ($appointment->grand_total ?? 0);
            $amountPaid = (float) ($appointment->amount_paid ?? 0);
            $due = AppointmentPayment::balanceDue($grandTotal, $amountPaid);
            $paymentStatus = (string) ($appointment->payment_status ?? AppointmentPayment::STATUS_UNPAID);

            $visitDate = $appointment->starts_at?->toDateString();
            $inRange = $visitDate !== null && $visitDate >= $from && $visitDate <= $to;

            if ($paymentStatus === AppointmentPayment::STATUS_PAID) {
                if ($inRange) {
                    $paidCount++;
                    $collected += $amountPaid;
                }

                continue;
            }

            if ($paymentStatus === AppointmentPayment::STATUS_REFUNDED || $due <= 0) {
                continue;
            }

            $pendingCount++;
            if ($inRange) {
                $collected += $amountPaid;
            }
            $outstanding += $due;

            $daysOverdue = $appointment->starts_at
                ? max((int) $appointment->starts_at->copy()->startOfDay()->diffInDays(now()->copy()->startOfDay(), false), 0)
                : 0;

            $canViewContact = CustomerContactAccess::canView($viewer, $appointment->customer);
            $hasPhone = filled($appointment->customer?->phone);
            $hasEmail = filled($appointment->customer?->email);

            $bill = [
                'appointment_id' => (int) $appointment->id,
                'type' => $appointment->type,
                'customer_id' => $appointment->customer_id !== null ? (int) $appointment->customer_id : null,
                'customer_name' => $appointment->customer?->name ?? 'Walk-in',
                'customer_phone' => $canViewContact ? $appointment->customer?->phone : null,
                'customer_email' => $canViewContact ? $appointment->customer?->email : null,
                'can_view_contact' => $canViewContact,
                'has_phone' => $hasPhone,
                'has_email' => $hasEmail,
                'branch_name' => $appointment->branch?->branch_name,
                'visit_date' => $visitDate,
                'grand_total' => round($grandTotal, 2),
                'amount_paid' => round($amountPaid, 2),
                'balance_due' => $due,
                'payment_status' => $paymentStatus,
                'days_overdue' => $daysOverdue,
                'is_overdue' => $daysOverdue >= $overdueDays && $overdueDays > 0,
                'can_remind' => $hasPhone || $hasEmail,
                'payment_reminder_sent_at' => $appointment->payment_reminder_sent_at?->toISOString(),
            ];
            $bills[] = $bill;

            $customerKey = $appointment->customer_id !== null
                ? 'c:'.$appointment->customer_id
                : 'walkin:'.$appointment->id;

            if (! isset($customers[$customerKey])) {
                $customers[$customerKey] = [
                    'customer_id' => $bill['customer_id'],
                    'name' => $bill['customer_name'],
                    'phone' => $bill['customer_phone'],
                    'email' => $bill['customer_email'],
                    'can_view_contact' => $canViewContact,
                    'has_phone' => $hasPhone,
                    'has_email' => $hasEmail,
                    'bills' => 0,
                    'outstanding' => 0.0,
                    'oldest_visit' => $visitDate,
                    'can_remind' => $bill['can_remind'],
                ];
            }

            $customers[$customerKey]['bills']++;
            $customers[$customerKey]['outstanding'] += $due;
            if ($visitDate && ($customers[$customerKey]['oldest_visit'] === null || $visitDate < $customers[$customerKey]['oldest_visit'])) {
                $customers[$customerKey]['oldest_visit'] = $visitDate;
            }
        }

        usort($bills, fn (array $a, array $b): int => ($b['balance_due'] <=> $a['balance_due']));

        $customerRows = array_map(static function (array $row): array {
            $row['outstanding'] = round($row['outstanding'], 2);

            return $row;
        }, array_values($customers));
        usort($customerRows, fn (array $a, array $b): int => ($b['outstanding'] <=> $a['outstanding']));

        return [
            'range' => ['from' => $from, 'to' => $to],
            'overdue_days' => $overdueDays,
            'paid_bills' => $paidCount,
            'pending_bills' => $pendingCount,
            'collected' => round($collected, 2),
            'outstanding' => round($outstanding, 2),
            'customers' => $customerRows,
            'bills' => $bills,
        ];
    }

    /**
     * Visits on or before the as-of date, used to keep older unpaid bills visible.
     *
     * @return Collection<int, Appointment>
     */
    private function bills(?int $saloonId, ?int $branchId, string $to): Collection
    {
        return Appointment::query()
            ->with(['customer', 'branch'])
            ->when($saloonId !== null, fn (Builder $query) => $query->where('saloon_id', $saloonId))
            ->when($branchId !== null, fn (Builder $query) => $query->where('branch_id', $branchId))
            ->whereDate('starts_at', '<=', $to)
            ->orderByDesc('starts_at')
            ->get();
    }
}
