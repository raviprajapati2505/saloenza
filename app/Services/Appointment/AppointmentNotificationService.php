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
                'customer' => 'Attach a customer with an email before sending a payment reminder.',
            ]);
        }

        if (! filled($customer->email)) {
            throw ValidationException::withMessages([
                'customer' => 'This customer has no email address on file.',
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

        return $this->notifications->sendMail(
            $salon,
            $customer->email,
            new OutstandingPaymentReminderMail($salon, $appointment, $customer, $due),
            $notificationKey,
            false,
        );
    }

    private function notifyCustomerReminder(Saloon $salon, Appointment $appointment): bool
    {
        $customer = $appointment->customer;
        if ($customer === null || ! $customer->email) {
            return false;
        }

        // TODO: WhatsApp — also send appointment reminder to customer phone when integrated.

        return $this->notifications->sendMail(
            $salon,
            $customer->email,
            new AppointmentReminderMail($salon, $appointment, $customer),
            'appointment_reminder',
        );
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

        // TODO: WhatsApp — send appointment confirmation to customer phone when integrated.
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

        // TODO: WhatsApp — send appointment cancellation notice to customer phone when integrated.
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
