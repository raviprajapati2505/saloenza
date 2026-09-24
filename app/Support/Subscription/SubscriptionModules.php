<?php

namespace App\Support\Subscription;

final class SubscriptionModules
{
    public const APPOINTMENTS = 'appointments';

    public const CUSTOMERS = 'customers';

    public const STAFF = 'staff';

    public const BILLING = 'billing';

    public const ANALYTICS = 'analytics';

    public const INVENTORY = 'inventory';

    public const CATALOG = 'catalog';

    public const ROLES = 'roles';

    public const SETTINGS = 'settings';

    public const QUEUE = 'queue';

    public const MARKETING = 'marketing';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::APPOINTMENTS,
            self::CUSTOMERS,
            self::STAFF,
            self::BILLING,
            self::ANALYTICS,
            self::INVENTORY,
            self::CATALOG,
            self::ROLES,
            self::SETTINGS,
            self::QUEUE,
            self::MARKETING,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            self::APPOINTMENTS => 'Appointments',
            self::CUSTOMERS => 'Customers',
            self::STAFF => 'Staff Management',
            self::BILLING => 'Billing',
            self::ANALYTICS => 'Analytics & Dashboard',
            self::INVENTORY => 'Inventory',
            self::CATALOG => 'Catalog & Services',
            self::ROLES => 'Roles & Permissions',
            self::SETTINGS => 'Settings',
            self::QUEUE => 'Live Queue',
            self::MARKETING => 'Marketing',
        ];
    }

    /**
     * Tenant permission codes gated by each module.
     *
     * @return array<string, list<string>>
     */
    public static function permissionMap(): array
    {
        return [
            self::APPOINTMENTS => [
                'appointments.view', 'appointments.create', 'appointments.update', 'appointments.delete',
                'booking_links.view', 'booking_links.manage',
                'waitlist.view', 'waitlist.manage',
                'pricing_rules.view', 'pricing_rules.manage',
                'payments.policy.manage', 'payments.charge', 'payments.refund', 'payments.waive',
            ],
            self::QUEUE => ['queue.view', 'queue.view_all'],
            self::CUSTOMERS => [
                'customers.view', 'customers.manage', 'customers.contacts.view',
                'crm.retention.view', 'crm.retention.manage',
                'packages.view', 'packages.manage', 'packages.sell', 'packages.redeem',
                'giftcards.view', 'giftcards.manage', 'giftcards.sell', 'giftcards.redeem', 'giftcards.adjust',
            ],
            self::STAFF => [
                'staff.view', 'staff.create', 'staff.update', 'staff.delete', 'staff.earnings.view',
                'commissions.view', 'commissions.manage', 'commissions.approve',
                'payroll.view', 'payroll.manage', 'payroll.approve',
                'attendance.view', 'attendance.manage', 'attendance.punch', 'leave.approve',
            ],
            self::BILLING => ['settings.view', 'settings.update'],
            self::ANALYTICS => ['analytics.view', 'analytics.benchmark.view', 'analytics.insights.view', 'expenses.view', 'expenses.manage'],
            self::INVENTORY => ['inventory.view', 'inventory.manage'],
            self::MARKETING => ['marketing.view', 'marketing.manage', 'marketing.send', 'reviews.view', 'reviews.manage'],
            self::CATALOG => [
                'categories.view', 'categories.create', 'categories.update', 'categories.delete',
                'services.view', 'services.create', 'services.update', 'services.delete',
                'products.view', 'products.create', 'products.update', 'products.delete',
            ],
            self::ROLES => [
                'roles.view', 'roles.create', 'roles.update', 'roles.delete',
                'assign_permissions.view', 'assign_permissions.update',
            ],
            self::SETTINGS => [
                'settings.view', 'settings.update',
                'branches.view', 'branches.create', 'branches.update', 'branches.delete',
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public static function permissionsForModules(array $modules): array
    {
        $codes = [];

        foreach ($modules as $module) {
            $codes = array_merge($codes, self::permissionMap()[$module] ?? []);
        }

        return array_values(array_unique($codes));
    }

    /**
     * Maps frontend route prefixes to subscription module keys.
     *
     * @return array<string, string>
     */
    public static function navModuleMap(): array
    {
        return [
            '/analytics' => self::ANALYTICS,
            '/reports' => self::ANALYTICS,
            '/insights' => self::ANALYTICS,
            '/retention' => self::CUSTOMERS,
            '/appointments' => self::APPOINTMENTS,
            '/pos' => self::APPOINTMENTS,
            '/queue' => self::QUEUE,
            '/staff/earnings' => self::STAFF,
            '/commissions' => self::STAFF,
            '/payroll' => self::STAFF,
            '/attendance' => self::STAFF,
            '/reviews' => self::MARKETING,
            '/booking-links' => self::APPOINTMENTS,
            '/waitlist' => self::APPOINTMENTS,
            '/no-show-policy' => self::APPOINTMENTS,
            '/pricing-rules' => self::APPOINTMENTS,
            '/customers' => self::CUSTOMERS,
            '/packages' => self::CUSTOMERS,
            '/gift-cards' => self::CUSTOMERS,
            '/staff' => self::STAFF,
            '/inventory' => self::INVENTORY,
            '/benchmarks' => self::ANALYTICS,
            '/marketing' => self::MARKETING,
            '/billing' => self::BILLING,
            '/categories' => self::CATALOG,
            '/catalog' => self::CATALOG,
            '/roles' => self::ROLES,
            '/assign-permissions' => self::ROLES,
            '/settings' => self::SETTINGS,
            '/branches' => self::SETTINGS,
        ];
    }

    /**
     * @return array{
     *     modules: list<array{key: string, label: string}>,
     *     nav_module_map: array<string, string>
     * }
     */
    public static function catalogPayload(): array
    {
        $labels = self::labels();

        return [
            'modules' => array_map(
                static fn (string $key): array => [
                    'key' => $key,
                    'label' => $labels[$key] ?? $key,
                ],
                self::all(),
            ),
            'nav_module_map' => self::navModuleMap(),
        ];
    }
}
