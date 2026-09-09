<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ApplicationPermissionSeeder extends Seeder
{
    /**
     * @return list<array{name: string, code: string, module: string, scope: string}>
     */
    public static function definitions(): array
    {
        return [
            ...self::crud('Categories', 'categories', 'tenant'),
            ...self::crud('Services', 'services', 'tenant'),
            ...self::crud('Products', 'products', 'tenant'),
            ...self::crud('Appointments', 'appointments', 'tenant'),
            ...self::crud('Staff', 'staff', 'tenant'),
            ...self::crud('Branches', 'branches', 'tenant'),
            ...self::crud('Roles', 'roles', 'tenant'),
            ['name' => 'View Customers', 'code' => 'customers.view', 'module' => 'customers', 'scope' => 'tenant'],
            ['name' => 'Manage Customers', 'code' => 'customers.manage', 'module' => 'customers', 'scope' => 'tenant'],
            ['name' => 'View Customer Contacts', 'code' => 'customers.contacts.view', 'module' => 'customers', 'scope' => 'tenant'],
            ['name' => 'View Analytics', 'code' => 'analytics.view', 'module' => 'analytics', 'scope' => 'tenant'],
            ['name' => 'View Expenses', 'code' => 'expenses.view', 'module' => 'expenses', 'scope' => 'tenant'],
            ['name' => 'Manage Expenses', 'code' => 'expenses.manage', 'module' => 'expenses', 'scope' => 'tenant'],
            ['name' => 'View Inventory', 'code' => 'inventory.view', 'module' => 'inventory', 'scope' => 'tenant'],
            ['name' => 'Manage Inventory', 'code' => 'inventory.manage', 'module' => 'inventory', 'scope' => 'tenant'],
            ['name' => 'View Assign Permissions', 'code' => 'assign_permissions.view', 'module' => 'assign_permissions', 'scope' => 'tenant'],
            ['name' => 'Update Assign Permissions', 'code' => 'assign_permissions.update', 'module' => 'assign_permissions', 'scope' => 'tenant'],
            ['name' => 'View Settings', 'code' => 'settings.view', 'module' => 'settings', 'scope' => 'tenant'],
            ['name' => 'Update Settings', 'code' => 'settings.update', 'module' => 'settings', 'scope' => 'tenant'],
            ['name' => 'View Referral Program', 'code' => 'referrals.view', 'module' => 'referrals', 'scope' => 'tenant'],
            ['name' => 'View Staff Earnings', 'code' => 'staff.earnings.view', 'module' => 'staff', 'scope' => 'tenant'],
            ['name' => 'View Live Queue', 'code' => 'queue.view', 'module' => 'queue', 'scope' => 'tenant'],
            ['name' => 'View Full Branch Queue', 'code' => 'queue.view_all', 'module' => 'queue', 'scope' => 'tenant'],
            ['name' => 'View Booking Links', 'code' => 'booking_links.view', 'module' => 'appointments', 'scope' => 'tenant'],
            ['name' => 'Manage Booking Links', 'code' => 'booking_links.manage', 'module' => 'appointments', 'scope' => 'tenant'],
            ['name' => 'View Affiliate Dashboard', 'code' => 'affiliate.dashboard.view', 'module' => 'affiliate_dashboard', 'scope' => 'affiliate'],
            ['name' => 'View Affiliate Referrals', 'code' => 'affiliate.referrals.view', 'module' => 'affiliate_referrals', 'scope' => 'affiliate'],
            ['name' => 'View Affiliate Commissions', 'code' => 'affiliate.commissions.view', 'module' => 'affiliate_commissions', 'scope' => 'affiliate'],
            ['name' => 'View Affiliate Reports', 'code' => 'affiliate.reports.view', 'module' => 'affiliate_reports', 'scope' => 'affiliate'],
            ['name' => 'Create Affiliate Withdrawals', 'code' => 'affiliate.withdrawals.create', 'module' => 'affiliate_withdrawals', 'scope' => 'affiliate'],
            ['name' => 'View Affiliate Withdrawals', 'code' => 'affiliate.withdrawals.view', 'module' => 'affiliate_withdrawals', 'scope' => 'affiliate'],
            ...self::crud('Salon Onboarding', 'platform.salon_onboarding', 'platform'),
            ...self::crud('Subscription Plans', 'platform.subscription_plans', 'platform'),
            ...self::crud('Affiliate Partners', 'platform.affiliates', 'platform'),
            ['name' => 'View Branches', 'code' => 'platform.branches.view', 'module' => 'branches', 'scope' => 'platform'],
            ['name' => 'Create Branches', 'code' => 'platform.branches.create', 'module' => 'branches', 'scope' => 'platform'],
            ['name' => 'Update Branches', 'code' => 'platform.branches.update', 'module' => 'branches', 'scope' => 'platform'],
            ['name' => 'View Upgrade Requests', 'code' => 'platform.upgrade_requests.view', 'module' => 'upgrade_requests', 'scope' => 'platform'],
            ['name' => 'Update Upgrade Requests', 'code' => 'platform.upgrade_requests.update', 'module' => 'upgrade_requests', 'scope' => 'platform'],
            ['name' => 'View Platform Reports', 'code' => 'platform.reports.view', 'module' => 'reports', 'scope' => 'platform'],
        ];
    }

    /**
     * @return list<string>
     */
    public static function allPermissionCodes(): array
    {
        return array_column(self::definitions(), 'code');
    }

    /**
     * @return list<string>
     */
    public static function tenantPermissionCodes(): array
    {
        return array_column(
            array_filter(self::definitions(), fn (array $permission): bool => $permission['scope'] === 'tenant'),
            'code',
        );
    }

    /**
     * @return list<string>
     */
    public static function managerPermissionCodes(): array
    {
        return array_values(array_filter(
            self::tenantPermissionCodes(),
            fn (string $code): bool => ! str_ends_with($code, '.delete'),
        ));
    }

    /**
     * Front-desk / stylist daily permissions (POS + appointments heavy).
     *
     * @return list<string>
     */
    public static function staffPermissionCodes(): array
    {
        return [
            'categories.view',
            'services.view',
            'products.view',
            'appointments.view',
            'appointments.create',
            'appointments.update',
            'customers.view',
            'customers.manage',
            'staff.earnings.view',
            'queue.view',
        ];
    }

    /**
     * Day-to-day ops for branch managers (staff perms + branch/staff/roles).
     *
     * @return list<string>
     */
    public static function branchManagerPermissionCodes(): array
    {
        return array_values(array_unique([
            ...self::staffPermissionCodes(),
            'branches.view',
            'branches.update',
            'staff.view',
            'staff.create',
            'staff.update',
            'roles.view',
            'roles.create',
            'roles.update',
            'roles.delete',
            'assign_permissions.view',
            'assign_permissions.update',
            'services.create',
            'services.update',
            'products.create',
            'products.update',
            'inventory.view',
            'inventory.manage',
            'analytics.view',
            'customers.contacts.view',
            'expenses.view',
            'expenses.manage',
            'booking_links.view',
            'booking_links.manage',
            'queue.view_all',
        ]));
    }

    /**
     * @return list<string>
     */
    public static function affiliatePermissionCodes(): array
    {
        return array_column(
            array_filter(self::definitions(), fn (array $permission): bool => $permission['scope'] === 'affiliate'),
            'code',
        );
    }

    /**
     * @return list<array{name: string, code: string, module: string, scope: string}>
     */
    private static function crud(string $name, string $code, string $scope): array
    {
        return [
            ['name' => "View {$name}", 'code' => "{$code}.view", 'module' => str_replace('platform.', '', $code), 'scope' => $scope],
            ['name' => "Create {$name}", 'code' => "{$code}.create", 'module' => str_replace('platform.', '', $code), 'scope' => $scope],
            ['name' => "Update {$name}", 'code' => "{$code}.update", 'module' => str_replace('platform.', '', $code), 'scope' => $scope],
            ['name' => "Delete {$name}", 'code' => "{$code}.delete", 'module' => str_replace('platform.', '', $code), 'scope' => $scope],
        ];
    }

    public function run(): void
    {
        DB::transaction(function (): void {
            DB::table('permission_role')->delete();
            Permission::query()->delete();

            foreach (self::definitions() as $permission) {
                Permission::query()->create($permission);
            }
        });
    }
}
