<?php

namespace App\Services\Appointment;

use App\Mail\AppointmentCancellationMail;
use App\Mail\AppointmentConfirmationMail;
use App\Mail\AppointmentReminderMail;
use App\Mail\OutstandingPaymentReminderMail;
use App\Mail\StaffAssignmentMail;
use App\Models\Appointment;
use App\Models\Saloon;
use App\Services\Tenant\TenantNotificationDispatcher;
use App\Support\Appointment\AppointmentPayment;
use Illuminate\Validation\ValidationException;

class AppointmentNotificationService
{
    public function __construct(
        private readonly TenantNotificationDispatcher $notifications,
    ) {}

    public function appointmentCreated(Appointment $appointment): void
    {
        $appointment->loadMissing(['saloon', 'customer', 'staff', 'service']);
        $salon = $appointment->saloon;

        if (! $salon instanceof Saloon) {
            return;
        }

        $this->notifyCustomerConfirmation($salon, $appointment);
        $this->notifyStaffAssignment($salon, $appointment);
    }

    public function appointmentUpdated(Appointment $appointment, ?string $previousStatus = null): void
    {
        $appointment->loadMissing(['saloon', 'customer', 'staff']);
        $salon = $appointment->saloon;

        if (! $salon instanceof Saloon) {
            return;
        }

        if ($previousStatus !== 'cancelled' && $appointment->status === 'cancelled') {
            $this->notifyCustomerCancellation($salon, $appointment);
        }
    }

    public function sendAppointmentReminder(Appointment $appointment): bool
    {
        $appointment->loadMissing(['saloon', 'customer', 'staff', 'service']);
        $salon = $appointment->saloon;

        if (! $salon instanceof Saloon) {
            return false;
        }

        return $this->notifyCustomerReminder($salon, $appointment);
    }

    public function sendOutstandingPaymentReminder(Appointment $appointment, bool $respectPreference = false): bool
    {
        $appointment->loadMissing(['saloon', 'customer']);
        $salon = $appointment->saloon;

        if (! $salon instanceof Saloon) {
            return false;
        }

        $customer = $appointment->customer;
        if ($customer === null) {
            throw ValidationException::withMessages([
                'customer' => 'Attach a customer with a phone or email before sending a payment reminder.',
            ]);
        }

        if (! filled($customer->email) && ! filled($customer->phone)) {
            throw ValidationException::withMessages([
                'customer' => 'This customer has no email or phone number on file.',
            ]);
        }

        $grandTotal = (float) ($appointment->grand_total ?? 0);
        $amountPaid = (float) ($appointment->amount_paid ?? 0);
        $due = AppointmentPayment::balanceDue($grandTotal, $amountPaid);

        if ($due <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'There is no outstanding balance on this visit.',
            ]);
        }

        $notificationKey = $respectPreference ? 'payment_outstanding_reminder' : null;
        $sent = false;

        if ($customer->email) {
            $sent = $this->notifications->sendMail(
                $salon,
                $customer->email,
                new OutstandingPaymentReminderMail($salon, $appointment, $customer, $due),
                $notificationKey,
                false,
            ) || $sent;
        }

        if ($customer->phone) {
            $sent = $this->notifications->sendSms(
                $salon,
                $customer->phone,
                sprintf(
                    '%s: Reminder — %s is still pending from your visit on %s.',
                    $salon->name,
                    number_format($due, 2),
                    $appointment->starts_at?->format('d M') ?? 'recently',
                ),
                $notificationKey,
                false,
            ) || $sent;
        }

        return $sent;
    }

    private function notifyCustomerReminder(Saloon $salon, Appointment $appointment): bool
    {
        $customer = $appointment->customer;
        if ($customer === null) {
            return false;
        }

        $sent = false;

        if ($customer->email) {
            $sent = $this->notifications->sendMail(
                $salon,
                $customer->email,
                new AppointmentReminderMail($salon, $appointment, $customer),
                'appointment_reminder',
            ) || $sent;
        }

        if ($customer->phone) {
            $sent = $this->notifications->sendSms(
                $salon,
                $customer->phone,
                sprintf(
                    '%s: Reminder — your appointment is on %s.',
                    $salon->name,
                    $appointment->starts_at?->format('d M, h:i A') ?? 'soon',
                ),
                'appointment_reminder',
            ) || $sent;
        }

        return $sent;
    }

    private function notifyCustomerConfirmation(Saloon $salon, Appointment $appointment): void
    {
        $customer = $appointment->customer;
        if ($customer === null) {
            return;
        }

        if ($customer->email) {
            $this->notifications->sendMail(
                $salon,
                $customer->email,
                new AppointmentConfirmationMail($salon, $appointment, $customer),
                'appointment_confirmation',
            );
        }

        if ($customer->phone) {
            $this->notifications->sendSms(
                $salon,
                $customer->phone,
                sprintf(
                    '%s: Your appointment is confirmed for %s.',
                    $salon->name,
                    $appointment->starts_at?->format('d M, h:i A') ?? 'soon',
                ),
                'appointment_confirmation',
            );
        }
    }

    private function notifyCustomerCancellation(Saloon $salon, Appointment $appointment): void
    {
        $customer = $appointment->customer;
        if ($customer === null) {
            return;
        }

        if ($customer->email) {
            $this->notifications->sendMail(
                $salon,
                $customer->email,
                new AppointmentCancellationMail($salon, $appointment, $customer),
                'booking_cancellation',
            );
        }

        if ($customer->phone) {
            $this->notifications->sendSms(
                $salon,
                $customer->phone,
                sprintf(
                    '%s: Your appointment on %s has been cancelled.',
                    $salon->name,
                    $appointment->starts_at?->format('d M, h:i A') ?? 'the scheduled time',
                ),
                'booking_cancellation',
            );
        }
    }

    private function notifyStaffAssignment(Saloon $salon, Appointment $appointment): void
    {
        $staff = $appointment->staff;
        if ($staff === null || ! $staff->email) {
            return;
        }

        $this->notifications->sendMail(
            $salon,
            $staff->email,
            new StaffAssignmentMail($salon, $appointment, $staff),
            'staff_assignment_alert',
        );
    }
}
