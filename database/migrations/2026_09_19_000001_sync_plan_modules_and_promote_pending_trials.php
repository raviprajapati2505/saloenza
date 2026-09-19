<?php

use App\Models\SubscriptionPlan;
use App\Models\SubscriptionUpgradeOrder;
use App\Support\Subscription\SubscriptionEntitlements;
use App\Support\Subscription\SubscriptionPlanCatalog;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        foreach (SubscriptionPlanCatalog::modulesBySlug() as $slug => $modules) {
            $plan = SubscriptionPlan::query()->where('slug', $slug)->first();
            if ($plan === null) {
                continue;
            }

            $plan->forceFill(['modules' => $modules])->save();
        }

        /** @var SubscriptionEntitlements $entitlements */
        $entitlements = app(SubscriptionEntitlements::class);

        $pendingOrders = SubscriptionUpgradeOrder::query()
            ->with(['saloon', 'toPlan'])
            ->whereIn('status', [
                SubscriptionUpgradeOrder::STATUS_PENDING,
                SubscriptionUpgradeOrder::STATUS_AWAITING_PAYMENT,
            ])
            ->get();

        foreach ($pendingOrders as $order) {
            $saloon = $order->saloon;
            $targetPlan = $order->toPlan;

            if ($saloon === null || $targetPlan === null || ! $targetPlan->isPaidPlan()) {
                continue;
            }

            if ((int) $targetPlan->trial_days <= 0) {
                continue;
            }

            $activePlan = $entitlements->activePlan($saloon);
            if ($activePlan !== null && (int) $activePlan->id === (int) $targetPlan->id) {
                $saloon->markActivated();

                continue;
            }

            $entitlements->assignPlan($saloon, $targetPlan, startTrial: true);
            $saloon->markActivated();
        }
    }

    public function down(): void
    {
        // Intentionally left blank — catalog sync and trial promotion are not reversible safely.
    }
};
