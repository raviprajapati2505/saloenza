<?php

namespace Database\Seeders;

use App\Models\SubscriptionPlan;
use App\Support\Subscription\SubscriptionPlanCatalog;
use Illuminate\Database\Seeder;

class SubscriptionPlanSeeder extends Seeder
{
    public function run(): void
    {
        // Migrate legacy slug so existing DBs keep continuity.
        SubscriptionPlan::query()
            ->where('slug', 'free-trial')
            ->update(['slug' => 'free']);

        foreach (SubscriptionPlanCatalog::definitions() as $definition) {
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
