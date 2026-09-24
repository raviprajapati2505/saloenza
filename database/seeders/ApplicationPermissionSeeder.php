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

            // Feature-gap modules (additive; backfill via PermissionBackfillSeeder)
            ['name' => 'View Commissions', 'code' => 'commissions.view', 'module' => 'commissions', 'scope' => 'tenant'],
            ['name' => 'Manage Commissions', 'code' => 'commissions.manage', 'module' => 'commissions', 'scope' => 'tenant'],
            ['name' => 'Approve Commission Payouts', 'code' => 'commissions.approve', 'module' => 'commissions', 'scope' => 'tenant'],
            ['name' => 'View Salon Insights', 'code' => 'analytics.insights.view', 'module' => 'analytics', 'scope' => 'tenant'],
            ['name' => 'View Branch Benchmarks', 'code' => 'analytics.benchmark.view', 'module' => 'analytics', 'scope' => 'tenant'],
            ['name' => 'View Retention', 'code' => 'crm.retention.view', 'module' => 'crm', 'scope' => 'tenant'],
            ['name' => 'Manage Retention', 'code' => 'crm.retention.manage', 'module' => 'crm', 'scope' => 'tenant'],
            ['name' => 'View Marketing', 'code' => 'marketing.view', 'module' => 'marketing', 'scope' => 'tenant'],
            ['name' => 'Manage Marketing', 'code' => 'marketing.manage', 'module' => 'marketing', 'scope' => 'tenant'],
            ['name' => 'Send Marketing Campaigns', 'code' => 'marketing.send', 'module' => 'marketing', 'scope' => 'tenant'],
            ['name' => 'View Packages', 'code' => 'packages.view', 'module' => 'packages', 'scope' => 'tenant'],
            ['name' => 'Manage Packages', 'code' => 'packages.manage', 'module' => 'packages', 'scope' => 'tenant'],
            ['name' => 'Sell Packages', 'code' => 'packages.sell', 'module' => 'packages', 'scope' => 'tenant'],
            ['name' => 'Redeem Packages', 'code' => 'packages.redeem', 'module' => 'packages', 'scope' => 'tenant'],
            ['name' => 'View Gift Cards', 'code' => 'giftcards.view', 'module' => 'giftcards', 'scope' => 'tenant'],
            ['name' => 'Manage Gift Cards', 'code' => 'giftcards.manage', 'module' => 'giftcards', 'scope' => 'tenant'],
            ['name' => 'Sell Gift Cards', 'code' => 'giftcards.sell', 'module' => 'giftcards', 'scope' => 'tenant'],
            ['name' => 'Redeem Gift Cards', 'code' => 'giftcards.redeem', 'module' => 'giftcards', 'scope' => 'tenant'],
            ['name' => 'Adjust Gift Cards', 'code' => 'giftcards.adjust', 'module' => 'giftcards', 'scope' => 'tenant'],
            ['name' => 'View Payroll', 'code' => 'payroll.view', 'module' => 'payroll', 'scope' => 'tenant'],
            ['name' => 'Manage Payroll', 'code' => 'payroll.manage', 'module' => 'payroll', 'scope' => 'tenant'],
            ['name' => 'Approve Payroll', 'code' => 'payroll.approve', 'module' => 'payroll', 'scope' => 'tenant'],
            ['name' => 'Manage No-Show Policies', 'code' => 'payments.policy.manage', 'module' => 'payments', 'scope' => 'tenant'],
            ['name' => 'Charge Client Payments', 'code' => 'payments.charge', 'module' => 'payments', 'scope' => 'tenant'],
            ['name' => 'Refund Client Payments', 'code' => 'payments.refund', 'module' => 'payments', 'scope' => 'tenant'],
            ['name' => 'Waive Client Fees', 'code' => 'payments.waive', 'module' => 'payments', 'scope' => 'tenant'],
            ['name' => 'View Waitlist', 'code' => 'waitlist.view', 'module' => 'waitlist', 'scope' => 'tenant'],
            ['name' => 'Manage Waitlist', 'code' => 'waitlist.manage', 'module' => 'waitlist', 'scope' => 'tenant'],
            ['name' => 'View Reviews', 'code' => 'reviews.view', 'module' => 'reviews', 'scope' => 'tenant'],
            ['name' => 'Manage Reviews', 'code' => 'reviews.manage', 'module' => 'reviews', 'scope' => 'tenant'],
            ['name' => 'View Attendance', 'code' => 'attendance.view', 'module' => 'attendance', 'scope' => 'tenant'],
            ['name' => 'Manage Attendance', 'code' => 'attendance.manage', 'module' => 'attendance', 'scope' => 'tenant'],
            ['name' => 'Punch Attendance', 'code' => 'attendance.punch', 'module' => 'attendance', 'scope' => 'tenant'],
            ['name' => 'Approve Leave', 'code' => 'leave.approve', 'module' => 'attendance', 'scope' => 'tenant'],
            ['name' => 'View Pricing Rules', 'code' => 'pricing_rules.view', 'module' => 'pricing', 'scope' => 'tenant'],
            ['name' => 'Manage Pricing Rules', 'code' => 'pricing_rules.manage', 'module' => 'pricing', 'scope' => 'tenant'],

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
            'packages.redeem',
            'giftcards.redeem',
            'waitlist.view',
            'waitlist.manage',
            'attendance.punch',
            'reviews.view',
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
            'commissions.view',
            'analytics.insights.view',
            'analytics.benchmark.view',
            'crm.retention.view',
            'crm.retention.manage',
            'marketing.view',
            'packages.view',
            'packages.sell',
            'packages.redeem',
            'giftcards.view',
            'giftcards.sell',
            'giftcards.redeem',
            'payroll.view',
            'payments.charge',
            'payments.waive',
            'waitlist.view',
            'waitlist.manage',
            'reviews.view',
            'reviews.manage',
            'attendance.view',
            'attendance.manage',
            'attendance.punch',
            'leave.approve',
            'pricing_rules.view',
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
