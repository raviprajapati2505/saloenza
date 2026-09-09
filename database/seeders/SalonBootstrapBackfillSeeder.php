<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\Saloon;
use App\Models\SaloonBranch;
use App\Models\SaloonSubscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Support\Role\RoleCodes;
use App\Support\Staff\DefaultStaffProvisioner;
use App\Support\Subscription\SubscriptionEntitlements;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

/**
 * Backfills missing subscriptions and default staff for existing salons.
 */
class SalonBootstrapBackfillSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('saloons') || ! Schema::hasTable('subscription_plans')) {
            return;
        }

        $this->call(SubscriptionPlanSeeder::class);

        $entitlements = app(SubscriptionEntitlements::class);
        $staffProvisioner = app(DefaultStaffProvisioner::class);

        $freePlan = SubscriptionPlan::query()->where('slug', 'free')->first();
        $proPlan = SubscriptionPlan::query()->where('slug', 'pro')->first();
        $staffRole = Role::findByCode(RoleCodes::SALON_STAFF);

        $fixedPlans = 0;
        $fixedStaff = 0;

        Saloon::query()->orderBy('id')->each(function (Saloon $saloon) use (
            $entitlements,
            $staffProvisioner,
            $freePlan,
            $proPlan,
            $staffRole,
            &$fixedPlans,
            &$fixedStaff,
        ): void {
            $hasAnySubscription = SaloonSubscription::query()
                ->where('saloon_id', $saloon->id)
                ->exists();

            if (! $hasAnySubscription) {
                $plan = $saloon->affiliate_partner_id !== null
                    ? ($proPlan ?? $freePlan)
                    : ($freePlan ?? $proPlan);

                if ($plan !== null) {
                    $entitlements->assignPlan(
                        $saloon,
                        $plan,
                        startTrial: $plan->slug === 'free' || (int) $plan->trial_days > 0,
                    );
                    $fixedPlans++;
                }
            }

            $branch = SaloonBranch::query()
                ->where('saloon_id', $saloon->id)
                ->orderBy('id')
                ->first();

            if ($branch === null || $staffRole === null) {
                return;
            }

            $hasStaff = User::query()
                ->where('saloon_id', $saloon->id)
                ->where('role_id', $staffRole->id)
                ->exists();

            if (! $hasStaff) {
                $staffProvisioner->ensureDefaultStaff($saloon, $branch, password: DemoDataSeeder::DEMO_PASSWORD);
                $fixedStaff++;
            }
        });

        if ($this->command !== null) {
            $this->command->info("Salon backfill complete: {$fixedPlans} plan(s), {$fixedStaff} default staff.");
        }
    }
}
