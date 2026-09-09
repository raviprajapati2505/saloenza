<?php

namespace Database\Seeders;

use App\Models\SubscriptionPlan;
use App\Support\Subscription\SubscriptionModules;
use Illuminate\Database\Seeder;

class SubscriptionPlanSeeder extends Seeder
{
    public function run(): void
    {
        // Migrate legacy slug so existing DBs keep continuity.
        SubscriptionPlan::query()
            ->where('slug', 'free-trial')
            ->update(['slug' => 'free']);

        $definitions = [
            [
                'name' => 'Free',
                'slug' => 'free',
                'description' => 'Setup-only plan for salon onboarding (1 month). Core modules while you configure the salon.',
                'price' => 0,
                'billing_interval' => 'monthly',
                'trial_days' => 30,
                'max_branches' => 1,
                'max_staff' => 3,
                'modules' => [
                    SubscriptionModules::APPOINTMENTS,
                    SubscriptionModules::CUSTOMERS,
                    SubscriptionModules::STAFF,
                    SubscriptionModules::CATALOG,
                    SubscriptionModules::ROLES,
                    SubscriptionModules::SETTINGS,
                ],
                'sort_order' => 0,
            ],
            [
                'name' => 'Basic',
                'slug' => 'basic',
                'description' => 'Single branch salon with essential modules. Optional free trial available.',
                'price' => 299,
                'billing_interval' => 'monthly',
                'trial_days' => 15,
                'max_branches' => 1,
                'max_staff' => 5,
                'modules' => [
                    SubscriptionModules::APPOINTMENTS,
                    SubscriptionModules::CUSTOMERS,
                    SubscriptionModules::STAFF,
                    SubscriptionModules::BILLING,
                    SubscriptionModules::INVENTORY,
                    SubscriptionModules::CATALOG,
                    SubscriptionModules::QUEUE,
                    SubscriptionModules::SETTINGS,
                ],
                'sort_order' => 10,
            ],
            [
                'name' => 'Pro',
                'slug' => 'pro',
                'description' => 'Growing salons with analytics and multi-branch support. Optional free trial available.',
                'price' => 499,
                'billing_interval' => 'monthly',
                'trial_days' => 15,
                'max_branches' => 3,
                'max_staff' => 20,
                'modules' => SubscriptionModules::all(),
                'sort_order' => 20,
            ],
            [
                'name' => 'Enterprise',
                'slug' => 'enterprise',
                'description' => 'Unlimited branches and staff with all modules. Optional free trial available.',
                'price' => 1499,
                'billing_interval' => 'monthly',
                'trial_days' => 15,
                'max_branches' => null,
                'max_staff' => null,
                'modules' => SubscriptionModules::all(),
                'sort_order' => 30,
            ],
        ];

        foreach ($definitions as $definition) {
            SubscriptionPlan::query()->updateOrCreate(
                ['slug' => $definition['slug']],
                array_merge($definition, [
                    'is_active' => true,
                    'is_public' => true,
                ]),
            );
        }
    }
}
