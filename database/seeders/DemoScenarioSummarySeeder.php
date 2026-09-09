<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Prints a consolidated catalog of all default demo logins after seeding.
 */
class DemoScenarioSummarySeeder extends Seeder
{
    public function run(): void
    {
        if ($this->command === null) {
            return;
        }

        $this->command->newLine();
        $this->command->info('Default demo scenario catalog');
        $this->command->line('Most salon accounts use password: <fg=cyan>'.DemoDataSeeder::DEMO_PASSWORD.'</>');
        $this->command->line('Platform super admin password: <fg=cyan>Admin@123</>');
        $this->command->newLine();

        $this->command->table(
            ['Scenario pack', 'Account', 'What to test'],
            [
                ['Platform', 'admin@salonos.com', 'Super admin, onboarding, platform settings'],
                ['Glow salon (full)', 'owner@glowdemo.com', 'Franchise owner — branches, POS, inventory, appointments'],
                ['Glow salon (full)', 'manager.andheri@glowdemo.com', 'Branch manager — Andheri scope'],
                ['Glow salon (full)', 'manager.bandra@glowdemo.com', 'Branch manager — Bandra scope'],
                ['Glow salon (full)', 'stylist.andheri@glowdemo.com', 'Staff — Andheri branch'],
                ['Glow salon (full)', 'stylist.bandra@glowdemo.com', 'Staff — Bandra branch'],
                ['Subscription · free active', SubscriptionDemoSeeder::FREE_ACTIVE_EMAIL, 'Free trial active (~15 days left)'],
                ['Subscription · free expired', SubscriptionDemoSeeder::FREE_EXPIRED_EMAIL, 'Free trial expired — portal locked'],
                ['Subscription · paid expired', SubscriptionDemoSeeder::PAID_EXPIRED_EMAIL, 'Paid plan expired — read-only mode'],
                ['Subscription · paid expiring', SubscriptionDemoSeeder::PAID_EXPIRING_EMAIL, 'Paid plan expiring — renewal reminder window'],
                ['Affiliate partners', '(see AffiliateDemoSeeder output above)', 'Partner portal, referrals, commissions, withdrawals'],
            ],
        );

        $this->command->newLine();
        $this->command->line('Re-run a single pack:');
        $this->command->line('  php artisan db:seed --class=DemoDataSeeder');
        $this->command->line('  php artisan db:seed --class=SubscriptionDemoSeeder');
        $this->command->line('  php artisan db:seed --class=AffiliateDemoSeeder');
    }
}
