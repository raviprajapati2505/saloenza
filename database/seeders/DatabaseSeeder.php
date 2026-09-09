<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed reference data plus every local demo scenario pack.
     */
    public function run(): void
    {
        $this->call([
            ApplicationPermissionSeeder::class,
            RoleSeeder::class,
            PlatformSettingsSeeder::class,
            SuperAdminSeeder::class,
            CategorySeeder::class,
            CatalogDataSeeder::class,
            SubscriptionPlanSeeder::class,
            DemoDataSeeder::class,
            SubscriptionDemoSeeder::class,
            AffiliateDemoSeeder::class,
            SalonBootstrapBackfillSeeder::class,
            DemoScenarioSummarySeeder::class,
        ]);
    }
}
