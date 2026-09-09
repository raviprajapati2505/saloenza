<?php

namespace App\Console\Commands;

use App\Models\Appointment;
use App\Models\Saloon;
use App\Services\Appointment\AppointmentNotificationService;
use App\Support\Tenant\TenantConfig;
use Illuminate\Console\Command;

class SendAppointmentReminders extends Command
{
    protected $signature = 'appointments:send-reminders
                            {--dry-run : List matching appointments without sending}';

    protected $description = 'Send appointment reminder notifications based on each salon tenant configuration';

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
            if (! $this->tenantConfig->notificationEnabled($salon, 'appointment_reminder', true)) {
                continue;
            }

            $hoursBefore = max(1, (int) $this->tenantConfig->get($salon, 'notifications', 'reminder_hours_before', 24));
            $windowStart = now()->addHours($hoursBefore);
            $windowEnd = $windowStart->copy()->addHour();

            $appointments = Appointment::query()
                ->with(['saloon', 'customer', 'staff', 'service', 'branch'])
                ->where('saloon_id', $salon->id)
                ->whereIn('status', ['scheduled', 'confirmed'])
                ->whereNull('reminder_sent_at')
                ->where('starts_at', '>', now())
                ->whereBetween('starts_at', [$windowStart, $windowEnd])
                ->orderBy('starts_at')
                ->get();

            foreach ($appointments as $appointment) {
                $label = sprintf(
                    '#%d %s @ %s',
                    $appointment->id,
                    $appointment->customer?->name ?? 'Walk-in',
                    $appointment->starts_at?->toDateTimeString() ?? 'n/a',
                );

                if ($dryRun) {
                    $this->line("[dry-run] Would remind ({$hoursBefore}h): {$salon->name} — {$label}");
                    $sent++;

                    continue;
                }

                $delivered = $this->appointmentNotifications->sendAppointmentReminder($appointment);

                if ($delivered) {
                    $appointment->update(['reminder_sent_at' => now()]);
                    $this->info("Reminded ({$hoursBefore}h): {$salon->name} — {$label}");
                    $sent++;
                } else {
                    $this->warn("Skipped (no contact): {$salon->name} — {$label}");
                    $skipped++;
                }
            }
        }

        $this->info(($dryRun ? 'Matched' : 'Sent').": {$sent}. Skipped: {$skipped}.");

        return self::SUCCESS;
    }
}
