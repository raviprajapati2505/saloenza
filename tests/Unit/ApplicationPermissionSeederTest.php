<?php

namespace Tests\Unit;

use App\Support\Subscription\SubscriptionModules;
use Database\Seeders\ApplicationPermissionSeeder;
use PHPUnit\Framework\TestCase;

class ApplicationPermissionSeederTest extends TestCase
{
    public function test_subscription_module_permission_map_only_references_defined_codes(): void
    {
        $defined = ApplicationPermissionSeeder::allPermissionCodes();

        foreach (SubscriptionModules::permissionMap() as $module => $codes) {
            foreach ($codes as $code) {
                $this->assertContains(
                    $code,
                    $defined,
                    "Module [{$module}] references undefined permission [{$code}].",
                );
            }
        }
    }

    public function test_branch_manager_pack_includes_new_operational_modules(): void
    {
        $codes = ApplicationPermissionSeeder::branchManagerPermissionCodes();

        $this->assertContains('inventory.view', $codes);
        $this->assertContains('inventory.manage', $codes);
        $this->assertContains('analytics.view', $codes);
        $this->assertContains('customers.contacts.view', $codes);
        $this->assertContains('queue.view_all', $codes);
    }

    public function test_staff_pack_covers_pos_and_payment_capture(): void
    {
        $codes = ApplicationPermissionSeeder::staffPermissionCodes();

        $this->assertContains('appointments.view', $codes);
        $this->assertContains('appointments.create', $codes);
        $this->assertContains('appointments.update', $codes);
        $this->assertContains('queue.view', $codes);
        $this->assertNotContains('customers.contacts.view', $codes);
    }
}
