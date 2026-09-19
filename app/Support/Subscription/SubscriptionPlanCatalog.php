<?php

namespace App\Support\Subscription;

/**
 * Canonical public plan definitions used by seeders and repair migrations.
 *
 * @phpstan-type PlanDefinition array{
 *     name: string,
 *     slug: string,
 *     description: string,
 *     price: float|int,
 *     billing_interval: string,
 *     trial_days: int,
 *     max_branches: int|null,
 *     max_staff: int|null,
 *     modules: list<string>,
 *     sort_order: int
 * }
 */
final class SubscriptionPlanCatalog
{
    /**
     * @return list<PlanDefinition>
     */
    public static function definitions(): array
    {
        return [
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
    }

    /**
     * @return array<string, list<string>>
     */
    public static function modulesBySlug(): array
    {
        $map = [];

        foreach (self::definitions() as $definition) {
            $map[$definition['slug']] = $definition['modules'];
        }

        return $map;
    }
}
