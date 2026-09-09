<?php

namespace App\Services\Appointment;

use App\Models\Appointment;
use App\Support\Appointment\AppointmentPayment;
use Illuminate\Validation\ValidationException;

class AppointmentPaymentService
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function fieldsForCreate(array $payload, float $grandTotal): array
    {
        return $this->buildFields($payload, $grandTotal, 0);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function fieldsForUpdate(Appointment $appointment, array $payload, float $grandTotal): array
    {
        if (! $this->payloadIncludesPayment($payload)) {
            return [];
        }

        $existingPaid = (float) ($appointment->amount_paid ?? 0);

        return $this->buildFields($payload, $grandTotal, $existingPaid);
    }

    public function capture(Appointment $appointment, float $amount, string $method): Appointment
    {
        if ($appointment->payment_status === AppointmentPayment::STATUS_REFUNDED) {
            throw ValidationException::withMessages([
                'amount' => 'This appointment was refunded. Edit the appointment before collecting payment again.',
            ]);
        }

        $grandTotal = (float) ($appointment->grand_total ?? 0);
        $balance = AppointmentPayment::balanceDue($grandTotal, (float) $appointment->amount_paid);

        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Payment amount must be greater than zero.',
            ]);
        }

        if ($amount > $balance + 0.009) {
            throw ValidationException::withMessages([
                'amount' => 'Payment exceeds the balance due.',
            ]);
        }

        $newPaid = round((float) $appointment->amount_paid + $amount, 2);
        $status = AppointmentPayment::resolveStatus($newPaid, $grandTotal);

        $appointment->update([
            'amount_paid' => $newPaid,
            'payment_method' => $method,
            'payment_status' => $status,
            'paid_at' => $status === AppointmentPayment::STATUS_PAID ? now() : $appointment->paid_at,
        ]);

        return $appointment->fresh();
    }

    public function refund(Appointment $appointment, ?float $amount = null): Appointment
    {
        $paid = (float) ($appointment->amount_paid ?? 0);

        if ($paid <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Nothing has been collected for this appointment.',
            ]);
        }

        $refundAmount = $amount === null ? $paid : round($amount, 2);

        if ($refundAmount <= 0 || $refundAmount > $paid + 0.009) {
            throw ValidationException::withMessages([
                'amount' => 'Invalid refund amount.',
            ]);
        }

        $newPaid = round($paid - $refundAmount, 2);
        $grandTotal = (float) ($appointment->grand_total ?? 0);
        $fullyRefunded = $newPaid <= 0;

        $appointment->update([
            'amount_paid' => max($newPaid, 0),
            'payment_status' => $fullyRefunded
                ? AppointmentPayment::STATUS_REFUNDED
                : AppointmentPayment::resolveStatus($newPaid, $grandTotal),
            'paid_at' => $fullyRefunded ? null : $appointment->paid_at,
        ]);

        return $appointment->fresh();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function payloadIncludesPayment(array $payload): bool
    {
        return (bool) ($payload['collect_payment'] ?? false);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function buildFields(array $payload, float $grandTotal, float $existingPaid): array
    {
        if (! (bool) ($payload['collect_payment'] ?? false)) {
            return [
                'payment_status' => AppointmentPayment::STATUS_UNPAID,
                'payment_method' => null,
                'amount_paid' => 0,
                'paid_at' => null,
            ];
        }

        $method = (string) ($payload['payment_method'] ?? '');
        if ($method === '') {
            throw ValidationException::withMessages([
                'payment_method' => 'Payment method is required when collecting payment.',
            ]);
        }

        $amount = array_key_exists('amount_paid', $payload) && $payload['amount_paid'] !== null
            ? round((float) $payload['amount_paid'], 2)
            : round($grandTotal, 2);

        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount_paid' => 'Payment amount must be greater than zero.',
            ]);
        }

        if ($amount > $grandTotal + 0.009) {
            throw ValidationException::withMessages([
                'amount_paid' => 'Payment exceeds the grand total.',
            ]);
        }

        $status = AppointmentPayment::resolveStatus($amount, $grandTotal);

        return [
            'payment_status' => $status,
            'payment_method' => $method,
            'amount_paid' => $amount,
            'paid_at' => $status === AppointmentPayment::STATUS_PAID ? now() : null,
        ];
    }
}
