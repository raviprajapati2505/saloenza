<?php

namespace App\Console\Commands;

use App\Models\Appointment;
use App\Models\Saloon;
use App\Services\Appointment\AppointmentNotificationService;
use App\Support\Appointment\AppointmentPayment;
use App\Support\Tenant\TenantConfig;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

class SendOutstandingPaymentReminders extends Command
{
    protected $signature = 'payments:send-outstanding-reminders
                            {--dry-run : List matching bills without sending}';

    protected $description = 'Send outstanding payment reminders after each salon’s overdue duration';

    public function __construct(
        private readonly TenantConfig $tenantConfig,
        private readonly AppointmentNotificationService $appointmentNotifications,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $sent = 0;
        $skipped = 0;

        $salons = Saloon::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->get();

        foreach ($salons as $salon) {
            if (! $this->tenantConfig->notificationEnabled($salon, 'payment_outstanding_reminder', false)) {
                continue;
            }

            $overdueDays = max(1, (int) $this->tenantConfig->get($salon, 'notifications', 'outstanding_overdue_days', 3));
            $cutoff = now()->subDays($overdueDays)->endOfDay();

            $appointments = Appointment::query()
                ->with(['saloon', 'customer'])
                ->where('saloon_id', $salon->id)
                ->whereNotIn('status', ['cancelled', 'no-show'])
                ->whereIn('payment_status', [AppointmentPayment::STATUS_UNPAID, AppointmentPayment::STATUS_PARTIAL])
                ->whereNull('payment_reminder_sent_at')
                ->where('starts_at', '<=', $cutoff)
                ->orderBy('starts_at')
                ->get();

            foreach ($appointments as $appointment) {
                $due = AppointmentPayment::balanceDue(
                    (float) ($appointment->grand_total ?? 0),
                    (float) ($appointment->amount_paid ?? 0),
                );

                if ($due <= 0 || $appointment->customer === null) {
                    $skipped++;

                    continue;
                }

                $label = sprintf(
                    '#%d %s due %s',
                    $appointment->id,
                    $appointment->customer->name ?? 'Customer',
                    number_format($due, 2),
                );

                if ($dryRun) {
                    $this->line("[dry-run] Would remind ({$overdueDays}d overdue): {$salon->name} — {$label}");
                    $sent++;

                    continue;
                }

                try {
                    $delivered = $this->appointmentNotifications->sendOutstandingPaymentReminder($appointment, true);
                } catch (ValidationException) {
                    $this->warn("Skipped: {$salon->name} — {$label}");
                    $skipped++;

                    continue;
                }

                if ($delivered) {
                    $appointment->update(['payment_reminder_sent_at' => now()]);
                    $this->info("Reminded ({$overdueDays}d overdue): {$salon->name} — {$label}");
                    $sent++;
                } else {
                    $this->warn("Skipped: {$salon->name} — {$label}");
                    $skipped++;
                }
            }
        }

        $this->info(($dryRun ? 'Matched' : 'Sent').": {$sent}. Skipped: {$skipped}.");

        return self::SUCCESS;
    }
}
