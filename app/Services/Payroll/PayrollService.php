<?php

namespace App\Services\Payroll;

use App\Models\PayRun;
use App\Models\PayRunLine;
use App\Models\User;
use App\Services\Staff\StaffEarningsService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class PayrollService
{
    public function __construct(
        private readonly StaffEarningsService $earnings,
    ) {}

    /**
     * @param  array{period_start: string, period_end: string, branch_id?: int|null, notes?: string|null}  $data
     */
    public function createDraft(User $actor, array $data): PayRun
    {
        $start = Carbon::parse($data['period_start'])->startOfDay();
        $end = Carbon::parse($data['period_end'])->endOfDay();

        if ($end->lt($start)) {
            throw ValidationException::withMessages([
                'period_end' => 'Period end must be on or after period start.',
            ]);
        }

        return PayRun::query()->create([
            'saloon_id' => (int) $actor->saloon_id,
            'branch_id' => $data['branch_id'] ?? null,
            'period_start' => $start->toDateString(),
            'period_end' => $end->toDateString(),
            'status' => PayRun::STATUS_DRAFT,
            'created_by' => (int) $actor->id,
            'notes' => $data['notes'] ?? null,
        ]);
    }

    public function calculate(PayRun $payRun): PayRun
    {
        if (! $payRun->isEditable()) {
            throw ValidationException::withMessages([
                'status' => 'Only draft pay runs can be recalculated.',
            ]);
        }

        $from = Carbon::parse($payRun->period_start)->startOfDay();
        $to = Carbon::parse($payRun->period_end)->endOfDay();
        $daysInPeriod = max(1, $from->diffInDays($to) + 1);

        $staff = $this->eligibleStaff($payRun);
        $payRun->lines()->delete();

        $totalBasic = 0.0;
        $totalCommission = 0.0;

        foreach ($staff as $member) {
            $monthly = (float) ($member->per_month_salary ?? 0);
            // Thin P0: prorate monthly salary by days in period / 30.
            $basic = round(($monthly / 30) * $daysInPeriod, 2);

            $commission = 0.0;
            $earningsSummary = null;
            try {
                $report = $this->earnings->report($member, $from, $to);
                $commission = round((float) ($report['summary']['commission_earned'] ?? 0), 2);
                $earningsSummary = $report['summary'] ?? null;
            } catch (\Throwable) {
                // Fall back to flat commission_rate if earnings fail — still produce a line.
                $commission = 0.0;
            }

            $gross = round($basic + $commission, 2);
            $totalBasic += $basic;
            $totalCommission += $commission;

            PayRunLine::query()->create([
                'pay_run_id' => $payRun->id,
                'user_id' => (int) $member->id,
                'branch_id' => $member->branch_id,
                'basic_salary' => $basic,
                'commission_total' => $commission,
                'gross' => $gross,
                'earnings_json' => [
                    'per_month_salary' => $monthly,
                    'days_in_period' => $daysInPeriod,
                    'commission_rate' => $member->commission_rate !== null
                        ? (float) $member->commission_rate
                        : null,
                    'earnings_summary' => $earningsSummary,
                ],
            ]);
        }

        $payRun->update([
            'total_basic' => round($totalBasic, 2),
            'total_commission' => round($totalCommission, 2),
            'total_gross' => round($totalBasic + $totalCommission, 2),
        ]);

        return $payRun->fresh(['lines.user', 'lines.branch', 'creator', 'approver', 'branch']);
    }

    public function approve(PayRun $payRun, User $actor): PayRun
    {
        if ($payRun->status !== PayRun::STATUS_DRAFT) {
            throw ValidationException::withMessages([
                'status' => 'Only draft pay runs can be approved.',
            ]);
        }

        if ($payRun->lines()->count() === 0) {
            throw ValidationException::withMessages([
                'lines' => 'Calculate pay run lines before approving.',
            ]);
        }

        $payRun->update([
            'status' => PayRun::STATUS_APPROVED,
            'approved_by' => (int) $actor->id,
            'approved_at' => now(),
        ]);

        return $payRun->fresh(['lines.user', 'lines.branch', 'creator', 'approver', 'branch']);
    }

    public function markPaid(PayRun $payRun): PayRun
    {
        if ($payRun->status !== PayRun::STATUS_APPROVED) {
            throw ValidationException::withMessages([
                'status' => 'Only approved pay runs can be marked paid.',
            ]);
        }

        $payRun->update([
            'status' => PayRun::STATUS_PAID,
            'paid_at' => now(),
        ]);

        return $payRun->fresh(['lines.user', 'lines.branch', 'creator', 'approver', 'branch']);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function csvRows(PayRun $payRun): array
    {
        $payRun->loadMissing(['lines.user', 'lines.branch']);

        $rows = [[
            'staff_name',
            'staff_email',
            'branch',
            'period_start',
            'period_end',
            'basic_salary',
            'commission_total',
            'gross',
            'status',
        ]];

        foreach ($payRun->lines as $line) {
            $rows[] = [
                $line->user?->name ?? '',
                $line->user?->email ?? '',
                $line->branch?->branch_name ?? '',
                $payRun->period_start?->toDateString(),
                $payRun->period_end?->toDateString(),
                number_format((float) $line->basic_salary, 2, '.', ''),
                number_format((float) $line->commission_total, 2, '.', ''),
                number_format((float) $line->gross, 2, '.', ''),
                $payRun->status,
            ];
        }

        return $rows;
    }

    /**
     * @return Collection<int, User>
     */
    private function eligibleStaff(PayRun $payRun): Collection
    {
        $query = User::query()
            ->staff()
            ->where('saloon_id', $payRun->saloon_id)
            ->where('is_active', true)
            ->orderBy('name');

        if ($payRun->branch_id !== null) {
            $query->where(function ($q) use ($payRun): void {
                $q->where('branch_id', $payRun->branch_id)
                    ->orWhereNull('branch_id');
            });
        }

        return $query->get();
    }
}
