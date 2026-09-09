<?php

namespace App\Console\Commands;

use App\Mail\SubscriptionRenewalReminderMail;
use App\Models\SaloonSubscription;
use App\Models\User;
use App\Services\Tenant\TenantNotificationDispatcher;
use App\Support\Role\RoleCodes;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class SendSubscriptionRenewalReminders extends Command
{
    protected $signature = 'subscriptions:send-renewal-reminders
                            {--days= : Optional single day window override}
                            {--dry-run : List matching subscriptions without sending}';

    protected $description = 'Notify salon owners whose subscription renews in the given number of days';

    public function __construct(
        private readonly TenantNotificationDispatcher $notifications,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $overrideDays = $this->option('days');
        $daysFilter = $overrideDays !== null && $overrideDays !== ''
            ? [max(1, (int) $overrideDays)]
            : null;
        $dryRun = (bool) $this->option('dry-run');
        $today = Carbon::today();

        $subscriptions = SaloonSubscription::query()
            ->with(['saloon', 'plan'])
            ->whereIn('status', [SaloonSubscription::STATUS_ACTIVE, SaloonSubscription::STATUS_TRIALING])
            ->where(function ($query): void {
                $query
                    ->where(function ($trialing): void {
                        $trialing
                            ->where('status', SaloonSubscription::STATUS_TRIALING)
                            ->whereNotNull('trial_ends_at');
                    })
                    ->orWhere(function ($active): void {
                        $active
                            ->where('status', SaloonSubscription::STATUS_ACTIVE)
                            ->whereNotNull('ends_at');
                    });
            })
            ->orderBy('id')
            ->get()
            ->filter(function (SaloonSubscription $subscription) use ($today, $daysFilter): bool {
                $plan = $subscription->plan;
                if ($plan === null || ! $plan->isPaidPlan()) {
                    return false;
                }

                $dueAt = $subscription->renewalDueAt();
                if ($dueAt === null) {
                    return false;
                }

                // Carbon may return a float; keep an int so strict day-window checks work.
                $daysRemaining = (int) round($today->diffInDays($dueAt->copy()->startOfDay(), false));
                if ($daysRemaining < 0) {
                    return false;
                }

                $allowedDays = $daysFilter ?? $this->reminderDaysForPlan((string) $plan->billing_interval);
                if (! in_array($daysRemaining, $allowedDays, true)) {
                    return false;
                }

                $alreadySent = array_map('intval', $subscription->renewal_reminder_days_sent ?? []);

                return ! in_array($daysRemaining, $alreadySent, true);
            });

        if ($subscriptions->isEmpty()) {
            $this->info('No matching subscriptions for reminder windows today.');

            return self::SUCCESS;
        }

        $sent = 0;
        $skipped = 0;

        foreach ($subscriptions as $subscription) {
            $daysRemaining = (int) round($today->diffInDays($subscription->renewalDueAt()?->copy()->startOfDay(), false));
            $owners = $this->ownersForSalon((int) $subscription->saloon_id);

            if ($owners->isEmpty()) {
                $this->warn("Skipping subscription #{$subscription->id}: no active salon owner found.");
                $skipped++;

                continue;
            }

            $label = sprintf(
                '#%d %s / %s → %s',
                $subscription->id,
                $subscription->saloon?->name ?? 'salon',
                $subscription->plan?->name ?? 'plan',
                $subscription->renewalDueAt()?->toDateString() ?? 'n/a',
            );

            if ($dryRun) {
                $this->line("[dry-run] Would remind ({$daysRemaining} days): {$label} ({$owners->count()} owner(s))");
                $sent++;

                continue;
            }

            foreach ($owners as $owner) {
                $salon = $subscription->saloon;
                if ($salon === null) {
                    continue;
                }

                $this->notifications->sendMail(
                    $salon,
                    $owner->email,
                    new SubscriptionRenewalReminderMail(
                        user: $owner,
                        saloon: $salon,
                        subscription: $subscription,
                        plan: $subscription->plan,
                        daysRemaining: $daysRemaining,
                    ),
                    'subscription_renewal_reminder',
                );
            }

            $alreadySent = array_map('intval', $subscription->renewal_reminder_days_sent ?? []);
            $alreadySent[] = $daysRemaining;
            $alreadySent = array_values(array_unique($alreadySent));
            sort($alreadySent);

            $subscription->update([
                'renewal_reminder_sent_at' => now(),
                'renewal_reminder_days_sent' => $alreadySent,
            ]);
            $this->info("Reminded ({$daysRemaining} days): {$label}");
            $sent++;
        }

        $this->info(($dryRun ? 'Matched' : 'Sent').": {$sent}. Skipped: {$skipped}.");

        return self::SUCCESS;
    }

    /**
     * @return \Illuminate\Support\Collection<int, User>
     */
    private function ownersForSalon(int $saloonId)
    {
        return User::query()
            ->where('saloon_id', $saloonId)
            ->where('is_active', true)
            ->whereHas('role', function ($query): void {
                $query->whereIn('code', [
                    RoleCodes::SALON_FRANCHISE_OWNER,
                    RoleCodes::LEGACY_SALOON_OWNER,
                ]);
            })
            ->get();
    }

    /**
     * @return list<int>
     */
    private function reminderDaysForPlan(string $billingInterval): array
    {
        if ($billingInterval === 'yearly') {
            return [30, 15, 7];
        }

        return [15, 7];
    }
}
