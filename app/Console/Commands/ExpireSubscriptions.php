<?php

namespace App\Console\Commands;

use App\Models\SaloonSubscription;
use App\Support\Subscription\SubscriptionEntitlements;
use Illuminate\Console\Command;

class ExpireSubscriptions extends Command
{
    protected $signature = 'subscriptions:expire
                            {--dry-run : List subscriptions that would be marked expired}';

    protected $description = 'Mark past-due salon subscriptions as expired and record history';

    public function handle(SubscriptionEntitlements $entitlements): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $subscriptions = SaloonSubscription::query()
            ->with(['saloon', 'plan'])
            ->whereIn('status', [SaloonSubscription::STATUS_ACTIVE, SaloonSubscription::STATUS_TRIALING])
            ->orderBy('id')
            ->get()
            ->filter(fn (SaloonSubscription $subscription) => ! $subscription->isCurrentlyActive());

        if ($subscriptions->isEmpty()) {
            $this->info('No subscriptions to expire.');

            return self::SUCCESS;
        }

        $expired = 0;

        foreach ($subscriptions as $subscription) {
            $saloon = $subscription->saloon;
            $planName = $subscription->plan?->name ?? 'plan';
            $label = sprintf(
                '#%d %s / %s (status=%s)',
                $subscription->id,
                $saloon?->name ?? 'salon',
                $planName,
                $subscription->status,
            );

            if ($dryRun) {
                $this->line("[dry-run] Would expire: {$label}");
                $expired++;

                continue;
            }

            $subscription->update([
                'status' => SaloonSubscription::STATUS_EXPIRED,
            ]);

            if ($saloon !== null) {
                $entitlements->recordHistory(
                    saloon: $saloon,
                    subscription: $subscription->fresh(),
                    planId: (int) $subscription->subscription_plan_id,
                    fromPlanId: null,
                    action: \App\Models\SaloonSubscriptionHistory::ACTION_EXPIRED,
                    notes: 'Subscription period ended.',
                );
            }

            $this->info("Expired: {$label}");
            $expired++;
        }

        $this->info(($dryRun ? 'Matched' : 'Expired').": {$expired}.");

        return self::SUCCESS;
    }
}
