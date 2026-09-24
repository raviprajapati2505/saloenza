<?php

namespace App\Services\NoShow;

use App\Models\Appointment;
use App\Models\Customer;
use App\Models\NoShowPolicy;
use App\Support\Appointment\AppointmentStatus;
use App\Support\NoShow\DepositStatus;
use App\Support\NoShow\NoShowFeeStatus;
use App\Support\NoShow\NoShowPolicyApplyMode;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class NoShowPolicyService
{
    public function getOrCreatePolicy(int $saloonId): NoShowPolicy
    {
        return NoShowPolicy::query()->firstOrCreate(
            ['saloon_id' => $saloonId],
            [
                'is_enabled' => false,
                'apply_mode' => NoShowPolicyApplyMode::REPEAT_OFFENDERS,
                'min_no_shows' => 2,
                'window_days' => 90,
                'protection_type' => 'deposit',
                'deposit_type' => 'fixed',
                'deposit_value' => 0,
                'fee_type' => 'percent',
                'fee_value' => 100,
                'cancel_cutoff_hours' => 24,
            ],
        );
    }

    /**
     * @return array{
     *     required: bool,
     *     amount: float,
     *     deposit_status: string,
     *     reason: ?string,
     *     policy: ?NoShowPolicy
     * }
     */
    public function evaluateDepositRequirement(
        int $saloonId,
        ?int $customerId,
        float $servicesTotal,
    ): array {
        $policy = NoShowPolicy::query()->where('saloon_id', $saloonId)->first();

        if ($policy === null || ! $policy->is_enabled) {
            return [
                'required' => false,
                'amount' => 0.0,
                'deposit_status' => DepositStatus::NOT_REQUIRED,
                'reason' => 'policy_disabled',
                'policy' => $policy,
            ];
        }

        $customer = $customerId ? Customer::query()->find($customerId) : null;

        if ($customer?->require_prepayment) {
            return $this->depositResult($policy, $servicesTotal, 'customer_require_prepayment');
        }

        if (! $this->customerMatchesPolicy($policy, $saloonId, $customer)) {
            return [
                'required' => false,
                'amount' => 0.0,
                'deposit_status' => DepositStatus::NOT_REQUIRED,
                'reason' => 'customer_not_in_scope',
                'policy' => $policy,
            ];
        }

        return $this->depositResult($policy, $servicesTotal, 'policy_match');
    }

    /**
     * Fields to merge onto appointment create/update when deposit applies.
     *
     * @return array<string, mixed>
     */
    public function depositFieldsForAppointment(
        int $saloonId,
        ?int $customerId,
        float $servicesTotal,
    ): array {
        $eval = $this->evaluateDepositRequirement($saloonId, $customerId, $servicesTotal);

        if (! $eval['required']) {
            return [
                'deposit_required_amount' => null,
                'deposit_status' => DepositStatus::NOT_REQUIRED,
                'deposit_paid_at' => null,
            ];
        }

        return [
            'deposit_required_amount' => $eval['amount'],
            'deposit_status' => DepositStatus::PENDING,
            'deposit_paid_at' => null,
        ];
    }

    /**
     * Compute fee fields when marking no-show (does not auto-persist status change).
     *
     * @return array{no_show_fee_amount: float, no_show_fee_status: string}
     */
    public function feeFieldsForNoShow(Appointment $appointment): array
    {
        $policy = NoShowPolicy::query()->where('saloon_id', $appointment->saloon_id)->first();
        $base = (float) ($appointment->services_total ?? $appointment->grand_total ?? $appointment->price ?? 0);

        if ($policy === null || ! $policy->is_enabled) {
            return [
                'no_show_fee_amount' => 0.0,
                'no_show_fee_status' => NoShowFeeStatus::NA,
            ];
        }

        $amount = $this->computeAmount(
            (string) $policy->fee_type,
            (float) $policy->fee_value,
            $base,
        );

        if ($appointment->deposit_status === DepositStatus::PAID && (float) $appointment->deposit_required_amount > 0) {
            $amount = max($amount, (float) $appointment->deposit_required_amount);
        }

        return [
            'no_show_fee_amount' => round($amount, 2),
            'no_show_fee_status' => $amount > 0 ? NoShowFeeStatus::PENDING : NoShowFeeStatus::NA,
        ];
    }

    public function markDepositPaid(Appointment $appointment): Appointment
    {
        if (! in_array($appointment->deposit_status, [DepositStatus::PENDING, DepositStatus::WAIVED], true)
            && $appointment->deposit_status !== DepositStatus::NOT_REQUIRED) {
            // Allow re-marking paid from pending/waived; also allow setting when amount exists.
        }

        $amount = (float) ($appointment->deposit_required_amount ?? 0);
        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'deposit' => 'No deposit amount is required on this appointment.',
            ]);
        }

        $appointment->update([
            'deposit_status' => DepositStatus::PAID,
            'deposit_paid_at' => now(),
        ]);

        return $appointment->fresh();
    }

    public function waiveDeposit(Appointment $appointment): Appointment
    {
        $appointment->update([
            'deposit_status' => DepositStatus::WAIVED,
            'deposit_paid_at' => null,
        ]);

        return $appointment->fresh();
    }

    public function refundDeposit(Appointment $appointment): Appointment
    {
        if ($appointment->deposit_status !== DepositStatus::PAID) {
            throw ValidationException::withMessages([
                'deposit' => 'Only a paid deposit can be refunded.',
            ]);
        }

        $appointment->update([
            'deposit_status' => DepositStatus::REFUNDED,
        ]);

        return $appointment->fresh();
    }

    public function markNoShowFee(Appointment $appointment, string $action = 'pending'): Appointment
    {
        $fields = $this->feeFieldsForNoShow($appointment);

        $status = match ($action) {
            'charged' => NoShowFeeStatus::CHARGED,
            'waived' => NoShowFeeStatus::WAIVED,
            'failed' => NoShowFeeStatus::FAILED,
            default => $fields['no_show_fee_status'],
        };

        $appointment->update([
            'no_show_fee_amount' => $fields['no_show_fee_amount'],
            'no_show_fee_status' => $status,
        ]);

        return $appointment->fresh();
    }

    public function chargeNoShowFee(Appointment $appointment): Appointment
    {
        return $this->markNoShowFee($appointment, 'charged');
    }

    public function waiveNoShowFee(Appointment $appointment): Appointment
    {
        return $this->markNoShowFee($appointment, 'waived');
    }

    private function customerMatchesPolicy(NoShowPolicy $policy, int $saloonId, ?Customer $customer): bool
    {
        return match ((string) $policy->apply_mode) {
            NoShowPolicyApplyMode::ALL_CLIENTS => true,
            NoShowPolicyApplyMode::HIGH_RISK => (bool) ($customer?->require_prepayment),
            NoShowPolicyApplyMode::TAGGED_ONLY => $customer !== null && $customer->tags()
                ->where(function ($query): void {
                    $query->where('customer_tags.name', 'like', '%no-show%')
                        ->orWhere('customer_tags.name', 'like', '%No-show%');
                })
                ->exists(),
            NoShowPolicyApplyMode::REPEAT_OFFENDERS => $this->noShowCount(
                $saloonId,
                $customer?->id,
                (int) $policy->window_days,
            ) >= (int) $policy->min_no_shows,
            default => false,
        };
    }

    private function noShowCount(int $saloonId, ?int $customerId, int $windowDays): int
    {
        if ($customerId === null) {
            return 0;
        }

        return Appointment::query()
            ->where('saloon_id', $saloonId)
            ->where('customer_id', $customerId)
            ->where('status', AppointmentStatus::NO_SHOW)
            ->where('starts_at', '>=', Carbon::now()->subDays(max(1, $windowDays)))
            ->count();
    }

    /**
     * @return array{
     *     required: bool,
     *     amount: float,
     *     deposit_status: string,
     *     reason: ?string,
     *     policy: NoShowPolicy
     * }
     */
    private function depositResult(NoShowPolicy $policy, float $servicesTotal, string $reason): array
    {
        $amount = $this->computeAmount(
            (string) $policy->deposit_type,
            (float) $policy->deposit_value,
            $servicesTotal,
        );

        if ($amount <= 0) {
            return [
                'required' => false,
                'amount' => 0.0,
                'deposit_status' => DepositStatus::NOT_REQUIRED,
                'reason' => 'zero_amount',
                'policy' => $policy,
            ];
        }

        return [
            'required' => true,
            'amount' => round($amount, 2),
            'deposit_status' => DepositStatus::PENDING,
            'reason' => $reason,
            'policy' => $policy,
        ];
    }

    private function computeAmount(string $type, float $value, float $base): float
    {
        if ($type === 'percent') {
            return max(0, round($base * ($value / 100), 2));
        }

        return max(0, round($value, 2));
    }
}
