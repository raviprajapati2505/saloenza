<?php

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\AppointmentService;
use App\Models\BranchProductStock;
use App\Models\Customer;
use App\Models\CustomerTag;
use App\Models\Expense;
use App\Models\Product;
use App\Models\Role;
use App\Models\SalonBookingLink;
use App\Models\SalonServiceProduct;
use App\Models\Saloon;
use App\Models\SaloonBranch;
use App\Models\Service;
use App\Models\SubscriptionPlan;
use App\Models\Supplier;
use App\Models\User;
use App\Support\Database\WriteRetry;
use App\Support\Expense\ExpenseCategory;
use App\Support\Role\RoleCodes;
use App\Support\Subscription\SubscriptionEntitlements;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

/**
 * Full-feature Glow demo salon (branches, staff, catalog, POS, inventory, appointments).
 *
 * Included in default `php artisan db:seed` along with subscription and affiliate scenario packs.
 *
 * Demo logins (password for all: Demo@123):
 * - Franchise owner:  owner@glowdemo.com
 * - Andheri manager:   manager.andheri@glowdemo.com
 * - Bandra manager:    manager.bandra@glowdemo.com
 * - Stylist (Andheri): stylist.andheri@glowdemo.com
 * - Stylist (Andheri 2): stylist2.andheri@glowdemo.com
 * - Stylist (Bandra):  stylist.bandra@glowdemo.com
 *
 * Other default scenario packs (see DatabaseSeeder):
 * - SubscriptionDemoSeeder — free/paid active, expired, expiring
 * - AffiliateDemoSeeder — partner portal, referrals, commissions, withdrawals
 *
 * Super admin (SuperAdminSeeder): admin@salonos.com / Admin@123
 */
class DemoDataSeeder extends Seeder
{
    public const DEMO_REFERRAL_CODE = 'GLOW-DEMO';

    public const DEMO_PASSWORD = 'Demo@123';

    public function run(): void
    {
        $this->call([
            PermissionSeeder::class,
            RoleSeeder::class,
            SuperAdminSeeder::class,
            SubscriptionPlanSeeder::class,
        ]);

        $masters = $this->seedMasterCatalog();
        $salon = $this->seedDemoSalon();
        $this->assignDemoSubscription($salon);
        $branches = $this->seedDemoBranches($salon);
        $this->seedSalonCatalog($salon, $branches, $masters);
        $users = $this->seedDemoUsers($salon, $branches);
        $customers = $this->seedDemoCustomers($salon, $users);
        $this->seedDemoAppointments($salon, $branches, $users, $customers, $masters);
        $this->seedDemoTodayQueue($salon, $branches, $users, $customers, $masters);
        $this->seedDemoBookingLinks($salon, $branches, $users);
        $this->seedDemoPaymentHistory($salon, $branches, $users, $customers, $masters);
        $suppliers = $this->seedDemoSuppliers($salon);
        $this->seedDemoBranchStock($branches, $masters, $suppliers);
        $this->seedDemoExpenses($salon, $branches);

        $this->printGlowSalonLogins();
    }

    private function printGlowSalonLogins(): void
    {
        if ($this->command === null) {
            return;
        }

        $this->command->newLine();
        $this->command->info('Glow Demo Salon seeded (password: '.self::DEMO_PASSWORD.'):');
        $this->command->table(
            ['Email', 'Role'],
            [
                ['owner@glowdemo.com', 'Franchise owner'],
                ['manager.andheri@glowdemo.com', 'Andheri branch manager'],
                ['manager.bandra@glowdemo.com', 'Bandra branch manager'],
                ['stylist.andheri@glowdemo.com', 'Stylist — Andheri'],
                ['stylist2.andheri@glowdemo.com', 'Stylist — Andheri (2nd chair)'],
                ['stylist.bandra@glowdemo.com', 'Stylist — Bandra'],
            ],
        );
    }

    public function seedDemoAffiliate(): void
    {
        $seeder = new AffiliateDemoSeeder;
        if ($this->command) {
            $seeder->setCommand($this->command);
        }
        $seeder->run();
    }

    /**
     * @return array{
     *     services: array<string, Service>,
     *     products: array<string, Product>,
     *     categories: array<string, Category>
     * }
     */
    private function seedMasterCatalog(): array
    {
        $catalog = new CatalogDataSeeder;
        if ($this->command) {
            $catalog->setCommand($this->command);
        }

        return $catalog->seedMasters();
    }

    private function seedDemoSalon(): Saloon
    {
        return Saloon::query()->updateOrCreate(
            ['referral_code' => self::DEMO_REFERRAL_CODE],
            [
                'name' => 'Glow Demo Salon',
                'branch_name' => 'Andheri West Flagship',
                'address' => 'Shop 12, Link Plaza, Andheri West',
                'city' => 'Mumbai',
                'state' => 'Maharashtra',
                'phone' => '+919876543210',
                'whatsapp' => '+919876543210',
                'gst_number' => '27AABCU9603R1ZM',
                'working_hours' => [
                    'mon' => ['09:00', '21:00'],
                    'tue' => ['09:00', '21:00'],
                    'wed' => ['09:00', '21:00'],
                    'thu' => ['09:00', '21:00'],
                    'fri' => ['09:00', '21:00'],
                    'sat' => ['10:00', '20:00'],
                    'sun' => ['10:00', '18:00'],
                ],
                'payment_type' => 'Yearly',
                'payment_amount' => 49999,
                'transaction_id' => 'DEMO-TXN-001',
                'is_active' => true,
            ],
        );
    }

    private function assignDemoSubscription(Saloon $salon): void
    {
        $plan = SubscriptionPlan::query()->where('slug', 'pro')->first();

        if ($plan === null) {
            return;
        }

        app(SubscriptionEntitlements::class)->assignPlan($salon, $plan);
    }

    /**
     * @return array{andheri: SaloonBranch, bandra: SaloonBranch}
     */
    private function seedDemoBranches(Saloon $salon): array
    {
        $andheri = SaloonBranch::query()->updateOrCreate(
            [
                'saloon_id' => $salon->id,
                'branch_name' => 'Andheri West',
            ],
            [
                'business_address_1' => 'Shop 12, Link Plaza',
                'business_address_2' => 'Near Metro Station',
                'city' => 'Mumbai',
                'state' => 'Maharashtra',
                'area_pincode' => '400053',
                'country' => 'India',
                'is_active' => true,
            ],
        );

        $bandra = SaloonBranch::query()->updateOrCreate(
            [
                'saloon_id' => $salon->id,
                'branch_name' => 'Bandra',
            ],
            [
                'business_address_1' => '18 Hill Road',
                'business_address_2' => 'Above Cafe',
                'city' => 'Mumbai',
                'state' => 'Maharashtra',
                'area_pincode' => '400050',
                'country' => 'India',
                'is_active' => true,
            ],
        );

        return ['andheri' => $andheri, 'bandra' => $bandra];
    }

    /**
     * Overheads for the last two months so the profit and loss report has a
     * realistic cost base rather than showing revenue as pure profit.
     *
     * @param  array{andheri: SaloonBranch, bandra: SaloonBranch}  $branches
     */
    private function seedDemoExpenses(Saloon $salon, array $branches): void
    {
        $rows = [
            ['branch' => 'andheri', 'category' => ExpenseCategory::RENT, 'title' => 'Andheri shop rent', 'amount' => 45000],
            ['branch' => 'andheri', 'category' => ExpenseCategory::UTILITIES, 'title' => 'Electricity & water', 'amount' => 7800],
            ['branch' => 'bandra', 'category' => ExpenseCategory::RENT, 'title' => 'Bandra shop rent', 'amount' => 62000],
            ['branch' => 'bandra', 'category' => ExpenseCategory::UTILITIES, 'title' => 'Electricity & water', 'amount' => 9400],
            ['branch' => null, 'category' => ExpenseCategory::MARKETING, 'title' => 'Instagram campaign', 'amount' => 12000],
            ['branch' => null, 'category' => ExpenseCategory::SOFTWARE, 'title' => 'Booking software licence', 'amount' => 2499],
            ['branch' => 'andheri', 'category' => ExpenseCategory::MAINTENANCE, 'title' => 'Chair reupholstery', 'amount' => 5600],
        ];

        foreach ([0, 1] as $monthsAgo) {
            $incurredOn = now()->subMonths($monthsAgo)->startOfMonth()->addDays(4);

            foreach ($rows as $row) {
                Expense::query()->updateOrCreate(
                    [
                        'saloon_id' => $salon->id,
                        'branch_id' => $row['branch'] !== null ? $branches[$row['branch']]->id : null,
                        'title' => $row['title'],
                        'incurred_on' => $incurredOn->toDateString(),
                    ],
                    [
                        'category' => $row['category'],
                        'amount' => $row['amount'],
                    ],
                );
            }
        }
    }

    /**
     * @param  array{andheri: SaloonBranch, bandra: SaloonBranch}  $branches
     * @param  array{services: array<string, Service>, products: array<string, Product>}  $masters
     */
    private function seedSalonCatalog(Saloon $salon, array $branches, array $masters): void
    {
        $catalog = new CatalogDataSeeder;
        if ($this->command) {
            $catalog->setCommand($this->command);
        }

        // Full catalog (service-only + all product variants + retail) on every branch.
        $catalog->attachMastersToSalon($salon, $branches, $masters);

        // Glow demo price overrides for the featured bookable offerings used in scenarios.
        $featuredOverrides = [
            ['service' => 'Haircut & Styling', 'product' => CatalogDataSeeder::GENERAL_ITEM, 'price' => 649, 'duration' => 45],
            ['service' => 'Hair Color', 'product' => 'Hair Color Kit — Natural Black', 'price' => 2699, 'duration' => 120],
            ['service' => 'Classic Facial', 'product' => 'Skincare Serum', 'price' => 999, 'duration' => 60],
            ['service' => 'Manicure', 'product' => 'Nail Polish Set', 'price' => 549, 'duration' => 40],
            ['service' => 'Beard Grooming', 'product' => 'Beard Oil', 'price' => 399, 'duration' => 30],
        ];

        foreach ($branches as $branch) {
            foreach ($featuredOverrides as $row) {
                if (! isset($masters['services'][$row['service']], $masters['products'][$row['product']])) {
                    continue;
                }

                $serviceId = $masters['services'][$row['service']]->id;

                SalonServiceProduct::query()->updateOrCreate(
                    [
                        'saloon_id' => $salon->id,
                        'branch_id' => $branch->id,
                        'service_id' => $serviceId,
                        'product_id' => null,
                    ],
                    [
                        'price' => $row['price'],
                        'duration_minutes' => $row['duration'],
                        'is_active' => true,
                    ],
                );

                SalonServiceProduct::query()->updateOrCreate(
                    [
                        'saloon_id' => $salon->id,
                        'branch_id' => $branch->id,
                        'service_id' => $serviceId,
                        'product_id' => $masters['products'][$row['product']]->id,
                    ],
                    [
                        'price' => $row['price'],
                        'duration_minutes' => $row['duration'],
                        'is_active' => true,
                    ],
                );
            }
        }
    }

    /**
     * @return array<string, Supplier>
     */
    private function seedDemoSuppliers(Saloon $salon): array
    {
        if (! Schema::hasTable('suppliers')) {
            return [];
        }

        $definitions = [
            'beauty_wholesale' => [
                'name' => 'Beauty Wholesale Co.',
                'contact_name' => 'Ravi Mehta',
                'phone' => '+919876543210',
                'email' => 'orders@beautywholesale.test',
            ],
            'salon_supply_hub' => [
                'name' => 'Salon Supply Hub',
                'contact_name' => 'Priya Shah',
                'phone' => '+919876543211',
                'email' => 'sales@supplyhub.test',
            ],
        ];

        $suppliers = [];
        foreach ($definitions as $key => $meta) {
            $suppliers[$key] = Supplier::query()->updateOrCreate(
                ['saloon_id' => $salon->id, 'name' => $meta['name']],
                array_merge($meta, ['is_active' => true]),
            );
        }

        return $suppliers;
    }

    /**
     * @param  array{andheri: SaloonBranch, bandra: SaloonBranch}  $branches
     * @param  array{products: array<string, Product>}  $masters
     * @param  array<string, Supplier>  $suppliers
     */
    private function seedDemoBranchStock(array $branches, array $masters, array $suppliers = []): void
    {
        $stockLevels = [
            'Professional Shampoo' => ['qty' => 24, 'reorder' => 6, 'cost' => 280, 'sell' => 650, 'supplier' => 'beauty_wholesale'],
            'Keratin Conditioner' => ['qty' => 16, 'reorder' => 5, 'cost' => 420, 'sell' => 890, 'supplier' => 'beauty_wholesale'],
            'Hair Serum' => ['qty' => 20, 'reorder' => 6, 'cost' => 310, 'sell' => 750, 'supplier' => 'salon_supply_hub'],
            'Hair Color Kit — Natural Black' => ['qty' => 10, 'reorder' => 3, 'cost' => 890, 'sell' => 1299, 'supplier' => 'salon_supply_hub'],
            'Hair Color Kit — Brown' => ['qty' => 8, 'reorder' => 3, 'cost' => 920, 'sell' => 1399, 'supplier' => 'salon_supply_hub'],
            'Hair Spa Mask' => ['qty' => 14, 'reorder' => 4, 'cost' => 380, 'sell' => 699, 'supplier' => 'beauty_wholesale'],
            'Skincare Serum' => ['qty' => 15, 'reorder' => 5, 'cost' => 420, 'sell' => 1200, 'supplier' => 'beauty_wholesale'],
            'Gold Facial Kit' => ['qty' => 6, 'reorder' => 2, 'cost' => 980, 'sell' => 1899, 'supplier' => 'beauty_wholesale'],
            'Nail Polish Set' => ['qty' => 12, 'reorder' => 4, 'cost' => 220, 'sell' => 450, 'supplier' => 'beauty_wholesale'],
            'Gel Polish Kit' => ['qty' => 9, 'reorder' => 3, 'cost' => 480, 'sell' => 980, 'supplier' => 'salon_supply_hub'],
            'Beard Oil' => ['qty' => 18, 'reorder' => 5, 'cost' => 160, 'sell' => 499, 'supplier' => 'salon_supply_hub'],
            'Shaving Cream' => ['qty' => 22, 'reorder' => 6, 'cost' => 90, 'sell' => 299, 'supplier' => 'salon_supply_hub'],
            'Aromatherapy Oil' => ['qty' => 11, 'reorder' => 4, 'cost' => 250, 'sell' => 599, 'supplier' => 'beauty_wholesale'],
            'Body Scrub' => ['qty' => 10, 'reorder' => 3, 'cost' => 340, 'sell' => 799, 'supplier' => 'beauty_wholesale'],
            CatalogDataSeeder::GENERAL_ITEM => ['qty' => 50, 'reorder' => 10, 'cost' => 40, 'sell' => 99, 'supplier' => 'salon_supply_hub'],
        ];

        foreach ($branches as $branch) {
            foreach ($stockLevels as $productName => $levels) {
                if (! isset($masters['products'][$productName])) {
                    continue;
                }

                $supplierId = isset($suppliers[$levels['supplier']]) ? $suppliers[$levels['supplier']]->id : null;

                BranchProductStock::query()->updateOrCreate(
                    [
                        'branch_id' => $branch->id,
                        'product_id' => $masters['products'][$productName]->id,
                    ],
                    array_filter([
                        'quantity_on_hand' => $levels['qty'],
                        'reorder_level' => $levels['reorder'],
                        'cost_price' => Schema::hasColumn('branch_product_stocks', 'cost_price') ? $levels['cost'] : null,
                        'selling_price' => Schema::hasColumn('branch_product_stocks', 'selling_price') ? $levels['sell'] : null,
                        'supplier_id' => Schema::hasColumn('branch_product_stocks', 'supplier_id') ? $supplierId : null,
                        'max_stock' => Schema::hasColumn('branch_product_stocks', 'max_stock') ? 100 : null,
                    ], fn ($value) => $value !== null),
                );
            }
        }
    }

    /**
     * @param  array{andheri: SaloonBranch, bandra: SaloonBranch}  $branches
     * @return array{
     *     owner: User,
     *     manager_andheri: User,
     *     manager_bandra: User,
     *     stylist_andheri: User,
     *     stylist_bandra: User
     * }
     */
    private function seedDemoUsers(Saloon $salon, array $branches): array
    {
        $ownerRole = Role::findByCode(RoleCodes::SALON_FRANCHISE_OWNER)
            ?? throw new \RuntimeException('Missing role: '.RoleCodes::SALON_FRANCHISE_OWNER);
        $managerRole = Role::findByCode(RoleCodes::SALON_BRANCH_MANAGER)
            ?? throw new \RuntimeException('Missing role: '.RoleCodes::SALON_BRANCH_MANAGER);
        $staffRole = Role::findByCode(RoleCodes::SALON_STAFF)
            ?? throw new \RuntimeException('Missing role: '.RoleCodes::SALON_STAFF);

        $password = self::DEMO_PASSWORD;

        $owner = $this->upsertUser([
            'email' => 'owner@glowdemo.com',
            'name' => 'Priya Sharma',
            'firstname' => 'Priya',
            'lastname' => 'Sharma',
            'phone' => '+919111111101',
            'role_id' => $ownerRole->id,
            'saloon_id' => $salon->id,
            'branch_id' => null,
            'password' => $password,
            'onboarding_completed_at' => now(),
        ]);

        $managerAndheri = $this->upsertUser([
            'email' => 'manager.andheri@glowdemo.com',
            'name' => 'Rahul Mehta',
            'firstname' => 'Rahul',
            'lastname' => 'Mehta',
            'phone' => '+919111111102',
            'role_id' => $managerRole->id,
            'saloon_id' => $salon->id,
            'branch_id' => $branches['andheri']->id,
            'password' => $password,
            'onboarding_completed_at' => now(),
        ]);

        $managerBandra = $this->upsertUser([
            'email' => 'manager.bandra@glowdemo.com',
            'name' => 'Anita Desai',
            'firstname' => 'Anita',
            'lastname' => 'Desai',
            'phone' => '+919111111103',
            'role_id' => $managerRole->id,
            'saloon_id' => $salon->id,
            'branch_id' => $branches['bandra']->id,
            'password' => $password,
            'onboarding_completed_at' => now(),
        ]);

        $stylistAndheri = $this->upsertUser([
            'email' => 'stylist.andheri@glowdemo.com',
            'name' => 'Karan Patel',
            'firstname' => 'Karan',
            'lastname' => 'Patel',
            'phone' => '+919111111104',
            'role_id' => $staffRole->id,
            'saloon_id' => $salon->id,
            'branch_id' => $branches['andheri']->id,
            'commission_rate' => 20,
            'password' => $password,
            'onboarding_completed_at' => now(),
        ]);

        $stylistAndheriTwo = $this->upsertUser([
            'email' => 'stylist2.andheri@glowdemo.com',
            'name' => 'Neha Singh',
            'firstname' => 'Neha',
            'lastname' => 'Singh',
            'phone' => '+919111111106',
            'role_id' => $staffRole->id,
            'saloon_id' => $salon->id,
            'branch_id' => $branches['andheri']->id,
            'commission_rate' => 18,
            'password' => $password,
            'onboarding_completed_at' => now(),
        ]);

        $stylistBandra = $this->upsertUser([
            'email' => 'stylist.bandra@glowdemo.com',
            'name' => 'Sneha Iyer',
            'firstname' => 'Sneha',
            'lastname' => 'Iyer',
            'phone' => '+919111111105',
            'role_id' => $staffRole->id,
            'saloon_id' => $salon->id,
            'branch_id' => $branches['bandra']->id,
            'commission_rate' => 22,
            'password' => $password,
            'onboarding_completed_at' => now(),
        ]);

        return [
            'owner' => $owner,
            'manager_andheri' => $managerAndheri,
            'manager_bandra' => $managerBandra,
            'stylist_andheri' => $stylistAndheri,
            'stylist_andheri_2' => $stylistAndheriTwo,
            'stylist_bandra' => $stylistBandra,
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function upsertUser(array $attributes): User
    {
        $user = WriteRetry::run(function () use ($attributes): User {
            $user = User::query()->firstOrNew(['email' => $attributes['email']]);
            $user->fill(array_merge([
                'email_verified_at' => now(),
                'is_active' => true,
            ], $attributes));
            $user->save();

            return $user;
        });

        return $user;
    }

    /**
     * @param  array{owner: User, stylist_andheri: User}  $users
     * @return array<string, Customer>
     */
    private function seedDemoCustomers(Saloon $salon, array $users): array
    {
        $customers = [];
        $today = now()->startOfDay();
        $definitions = [
            'aditi' => [
                'name' => 'Aditi Verma',
                'email' => 'aditi@example.com',
                'phone' => '+919222222201',
                'birthday' => $today->toDateString(),
                'anniversary' => $today->copy()->subMonths(4)->toDateString(),
                'created_by' => $users['stylist_andheri']->id,
            ],
            'rohan' => [
                'name' => 'Rohan Kapoor',
                'email' => 'rohan@example.com',
                'phone' => '+919222222202',
                'birthday' => $today->copy()->subDays(40)->toDateString(),
                'anniversary' => $today->toDateString(),
                'created_by' => $users['owner']->id,
            ],
            'meera' => [
                'name' => 'Meera Nair',
                'email' => 'meera@example.com',
                'phone' => '+919222222203',
                'birthday' => $today->copy()->addDays(12)->toDateString(),
                'anniversary' => null,
                'created_by' => $users['owner']->id,
            ],
            'vikram' => [
                'name' => 'Vikram Singh',
                'email' => 'vikram@example.com',
                'phone' => '+919222222204',
                'birthday' => $today->copy()->subMonths(2)->toDateString(),
                'anniversary' => $today->copy()->addDays(20)->toDateString(),
                'created_by' => $users['owner']->id,
            ],
        ];

        foreach ($definitions as $key => $row) {
            $customer = Customer::query()->updateOrCreate(
                [
                    'phone' => $row['phone'],
                ],
                [
                    'name' => $row['name'],
                    'email' => $row['email'],
                    'notes' => 'Demo customer',
                    'is_active' => true,
                    'birthday' => $row['birthday'],
                    'anniversary' => $row['anniversary'],
                    'created_by' => $row['created_by'],
                ],
            );

            $customer->attachSaloon((int) $salon->id);
            $customers[$key] = $customer;
        }

        $tags = [];
        if (Schema::hasTable('customer_tags') && Schema::hasTable('customer_customer_tag')) {
            $tags = [
                'vip' => CustomerTag::query()->updateOrCreate(
                    ['saloon_id' => $salon->id, 'name' => 'VIP'],
                    ['color' => 'amber'],
                ),
                'regular' => CustomerTag::query()->updateOrCreate(
                    ['saloon_id' => $salon->id, 'name' => 'Regular'],
                    ['color' => 'slate'],
                ),
            ];

            $customers['aditi']->tags()->syncWithoutDetaching([$tags['vip']->id]);
            $customers['rohan']->tags()->syncWithoutDetaching([$tags['regular']->id]);
            $customers['meera']->tags()->syncWithoutDetaching([$tags['vip']->id, $tags['regular']->id]);
        }

        return $customers;
    }

    /**
     * @param  array{andheri: SaloonBranch, bandra: SaloonBranch}  $branches
     * @param  array{stylist_andheri: User, stylist_bandra: User}  $users
     * @param  array<string, Customer>  $customers
     * @param  array{services: array<string, Service>}  $masters
     */
    private function seedDemoAppointments(
        Saloon $salon,
        array $branches,
        array $users,
        array $customers,
        array $masters,
    ): void {
        $tomorrow = now()->addDay()->startOfDay();
        $definitions = [
            [
                'branch' => $branches['andheri'],
                'customer' => $customers['aditi'],
                'staff' => $users['stylist_andheri'],
                'service' => $masters['services']['Haircut & Styling'],
                'start' => $tomorrow->copy()->setTime(10, 0),
                'status' => 'scheduled',
                'price' => 649,
            ],
            [
                'branch' => $branches['andheri'],
                'customer' => $customers['rohan'],
                'staff' => $users['stylist_andheri'],
                'service' => $masters['services']['Beard Grooming'],
                'start' => $tomorrow->copy()->setTime(11, 30),
                'status' => 'confirmed',
                'price' => 399,
            ],
            [
                'branch' => $branches['bandra'],
                'customer' => $customers['meera'],
                'staff' => $users['stylist_bandra'],
                'service' => $masters['services']['Classic Facial'],
                'start' => $tomorrow->copy()->setTime(14, 0),
                'status' => 'scheduled',
                'price' => 999,
            ],
            [
                'branch' => $branches['bandra'],
                'customer' => $customers['vikram'],
                'staff' => $users['stylist_bandra'],
                'service' => $masters['services']['Hair Color'],
                'start' => $tomorrow->copy()->setTime(16, 0),
                'status' => 'scheduled',
                'price' => 2699,
            ],
        ];

        foreach ($definitions as $row) {
            $startsAt = $row['start'];
            $endsAt = $startsAt->copy()->addMinutes((int) $row['service']->duration_minutes);

            $appointment = Appointment::query()->updateOrCreate(
                [
                    'saloon_id' => $salon->id,
                    'branch_id' => $row['branch']->id,
                    'customer_id' => $row['customer']->id,
                    'staff_id' => $row['staff']->id,
                    'starts_at' => $startsAt,
                ],
                [
                    'service_id' => $row['service']->id,
                    'ends_at' => $endsAt,
                    'status' => $row['status'],
                    'price' => $row['price'],
                    'discount' => 0,
                    'grand_total' => $row['price'],
                    'payment_status' => 'unpaid',
                    'amount_paid' => 0,
                    'notes' => 'Demo appointment',
                    'type' => Appointment::TYPE_APPOINTMENT,
                    'booking_source' => Appointment::SOURCE_INTERNAL,
                ],
            );

            $this->syncDemoAppointmentServiceLine($appointment, $row['service'], $row['staff']);
        }
    }

    private function syncDemoAppointmentServiceLine(
        Appointment $appointment,
        Service $service,
        User $staff,
        ?int $productId = null,
    ): void {
        $duration = (int) ($appointment->ends_at?->diffInMinutes($appointment->starts_at) ?: $service->duration_minutes);

        AppointmentService::query()->updateOrCreate(
            [
                'appointment_id' => $appointment->id,
                'service_id' => $service->id,
            ],
            [
                'staff_id' => $staff->id,
                'product_id' => $productId,
                'price' => $appointment->price,
                'duration_minutes' => max(1, $duration),
                'starts_at' => $appointment->starts_at,
                'ends_at' => $appointment->ends_at,
                'sort_order' => 0,
            ],
        );
    }

    /**
     * Today's floor queue with realistic statuses for live queue / staff demos.
     *
     * @param  array{andheri: SaloonBranch, bandra: SaloonBranch}  $branches
     * @param  array{stylist_andheri: User, stylist_bandra: User, manager_andheri?: User}  $users
     * @param  array<string, Customer>  $customers
     * @param  array{services: array<string, Service>}  $masters
     */
    private function seedDemoTodayQueue(
        Saloon $salon,
        array $branches,
        array $users,
        array $customers,
        array $masters,
    ): void {
        $definitions = [
            [
                'branch' => $branches['andheri'],
                'customer' => $customers['aditi'],
                'staff' => $users['stylist_andheri'],
                'service' => $masters['services']['Haircut & Styling'],
                'start' => now()->subMinutes(25),
                'status' => 'in-progress',
                'price' => 649,
                'booking_source' => Appointment::SOURCE_INTERNAL,
            ],
            [
                'branch' => $branches['andheri'],
                'customer' => $customers['rohan'],
                'staff' => $users['stylist_andheri'],
                'service' => $masters['services']['Beard Grooming'],
                'start' => now()->addMinutes(10),
                'status' => 'confirmed',
                'price' => 399,
                'booking_source' => Appointment::SOURCE_SELF_BOOKING,
            ],
            [
                'branch' => $branches['andheri'],
                'customer' => $customers['meera'],
                'staff' => $users['stylist_andheri'],
                'service' => $masters['services']['Classic Facial'],
                'start' => now()->addHours(3),
                'status' => 'scheduled',
                'price' => 999,
                'booking_source' => Appointment::SOURCE_INTERNAL,
            ],
            [
                'branch' => $branches['andheri'],
                'customer' => $customers['vikram'],
                'staff' => $users['stylist_andheri_2'],
                'service' => $masters['services']['Hair Color'],
                'start' => now()->subMinutes(10),
                'status' => 'confirmed',
                'price' => 2699,
                'booking_source' => Appointment::SOURCE_WALK_IN,
                'type' => Appointment::TYPE_WALK_IN,
            ],
            [
                'branch' => $branches['andheri'],
                'customer' => $customers['aditi'],
                'staff' => $users['stylist_andheri'],
                'service' => $masters['services']['Haircut & Styling'],
                'start' => now()->addMinutes(45),
                'status' => 'confirmed',
                'price' => 1648,
                'booking_source' => Appointment::SOURCE_INTERNAL,
                'combo' => [
                    [
                        'service' => $masters['services']['Haircut & Styling'],
                        'staff' => $users['stylist_andheri'],
                        'price' => 649,
                    ],
                    [
                        'service' => $masters['services']['Classic Facial'],
                        'staff' => $users['stylist_andheri_2'],
                        'price' => 999,
                    ],
                ],
            ],
        ];

        foreach ($definitions as $row) {
            $startsAt = $row['start'];
            $duration = (int) $row['service']->duration_minutes;
            $endsAt = $startsAt->copy()->addMinutes($duration);
            $type = $row['type'] ?? Appointment::TYPE_APPOINTMENT;
            $status = $row['status'];

            $appointment = Appointment::query()->updateOrCreate(
                [
                    'saloon_id' => $salon->id,
                    'branch_id' => $row['branch']->id,
                    'customer_id' => $row['customer']->id,
                    'starts_at' => $startsAt,
                    'type' => $type,
                ],
                [
                    'staff_id' => $row['staff']->id,
                    'service_id' => $row['service']->id,
                    'ends_at' => $endsAt,
                    'status' => $status,
                    'price' => $row['price'],
                    'discount' => 0,
                    'grand_total' => $row['price'],
                    'payment_status' => $type === Appointment::TYPE_WALK_IN && $status === 'completed' ? 'paid' : 'unpaid',
                    'amount_paid' => $type === Appointment::TYPE_WALK_IN && $status === 'completed' ? $row['price'] : 0,
                    'notes' => 'Demo queue appointment',
                    'booking_source' => $row['booking_source'],
                ],
            );

            $combo = $row['combo'] ?? null;

            if (is_array($combo) && $combo !== []) {
                foreach ($combo as $index => $line) {
                    $lineDuration = (int) $line['service']->duration_minutes;
                    $lineStarts = $startsAt->copy()->addMinutes(
                        $index > 0
                            ? (int) array_sum(array_map(
                                fn (array $prev): int => (int) $prev['service']->duration_minutes,
                                array_slice($combo, 0, $index),
                            ))
                            : 0,
                    );
                    $lineEnds = $lineStarts->copy()->addMinutes($lineDuration);

                    AppointmentService::query()->updateOrCreate(
                        [
                            'appointment_id' => $appointment->id,
                            'service_id' => $line['service']->id,
                        ],
                        [
                            'staff_id' => $line['staff']->id,
                            'price' => $line['price'],
                            'duration_minutes' => $lineDuration,
                            'starts_at' => $lineStarts,
                            'ends_at' => $lineEnds,
                            'sort_order' => $index,
                        ],
                    );
                }

                $totalDuration = array_sum(array_map(
                    fn (array $line): int => (int) $line['service']->duration_minutes,
                    $combo,
                ));

                $appointment->update([
                    'staff_id' => $combo[0]['staff']->id,
                    'service_id' => $combo[0]['service']->id,
                    'ends_at' => $startsAt->copy()->addMinutes($totalDuration),
                    'services_total' => array_sum(array_map(
                        fn (array $line): int => (int) $line['price'],
                        $combo,
                    )),
                ]);

                continue;
            }

            AppointmentService::query()->updateOrCreate(
                [
                    'appointment_id' => $appointment->id,
                    'service_id' => $row['service']->id,
                ],
                [
                    'staff_id' => $row['staff']->id,
                    'price' => $row['price'],
                    'duration_minutes' => $duration,
                    'starts_at' => $startsAt,
                    'ends_at' => $endsAt,
                    'sort_order' => 0,
                ],
            );
        }
    }

    /**
     * @param  array{andheri: SaloonBranch, bandra: SaloonBranch}  $branches
     * @param  array{owner: User, manager_andheri?: User}  $users
     */
    private function seedDemoBookingLinks(Saloon $salon, array $branches, array $users): void
    {
        $owner = $users['owner'] ?? User::query()->where('email', 'owner@glowdemo.com')->first();

        SalonBookingLink::query()->updateOrCreate(
            ['token' => 'glowdemo-andheri-book'],
            [
                'saloon_id' => $salon->id,
                'branch_id' => $branches['andheri']->id,
                'label' => 'Andheri customer booking link',
                'is_active' => true,
                'created_by' => $owner?->id,
            ],
        );

        SalonBookingLink::query()->updateOrCreate(
            ['token' => 'glowdemo-bandra-book'],
            [
                'saloon_id' => $salon->id,
                'branch_id' => $branches['bandra']->id,
                'label' => 'Bandra customer booking link',
                'is_active' => true,
                'created_by' => $owner?->id,
            ],
        );
    }

    /**
     * Past completed appointments with varied payment states for reports/POS demos.
     *
     * @param  array<string, SaloonBranch>  $branches
     * @param  array<string, User>  $users
     * @param  array<string, Customer>  $customers
     * @param  array{services: array<string, Service>}  $masters
     */
    private function seedDemoPaymentHistory(
        Saloon $salon,
        array $branches,
        array $users,
        array $customers,
        array $masters,
    ): void {
        $definitions = [
            [
                'branch' => $branches['andheri'],
                'customer' => $customers['aditi'],
                'staff' => $users['stylist_andheri'],
                'service' => $masters['services']['Haircut & Styling'],
                'start' => now()->subDays(2)->setTime(11, 0),
                'price' => 649,
                'payment_status' => 'paid',
                'payment_method' => 'upi',
                'amount_paid' => 649,
            ],
            [
                'branch' => $branches['andheri'],
                'customer' => $customers['rohan'],
                'staff' => $users['stylist_andheri'],
                'service' => $masters['services']['Beard Grooming'],
                'start' => now()->subDay()->setTime(15, 30),
                'price' => 399,
                'payment_status' => 'partial',
                'payment_method' => 'cash',
                'amount_paid' => 200,
            ],
            [
                'branch' => $branches['bandra'],
                'customer' => $customers['meera'],
                'staff' => $users['stylist_bandra'],
                'service' => $masters['services']['Classic Facial'],
                'start' => now()->subDays(3)->setTime(13, 0),
                'price' => 999,
                'payment_status' => 'paid',
                'payment_method' => 'card',
                'amount_paid' => 999,
            ],
            [
                'branch' => $branches['bandra'],
                'customer' => $customers['vikram'],
                'staff' => $users['stylist_bandra'],
                'service' => $masters['services']['Hair Color'],
                'start' => now()->subDays(1)->setTime(17, 0),
                'price' => 2699,
                'payment_status' => 'unpaid',
                'payment_method' => null,
                'amount_paid' => 0,
            ],
        ];

        foreach ($definitions as $row) {
            $startsAt = $row['start'];
            $endsAt = $startsAt->copy()->addMinutes((int) $row['service']->duration_minutes);
            $paidAt = $row['amount_paid'] > 0 ? $startsAt->copy()->addMinutes(5) : null;

            Appointment::query()->updateOrCreate(
                [
                    'saloon_id' => $salon->id,
                    'branch_id' => $row['branch']->id,
                    'customer_id' => $row['customer']->id,
                    'staff_id' => $row['staff']->id,
                    'starts_at' => $startsAt,
                ],
                [
                    'service_id' => $row['service']->id,
                    'ends_at' => $endsAt,
                    'status' => 'completed',
                    'price' => $row['price'],
                    'discount' => 0,
                    'grand_total' => $row['price'],
                    'payment_status' => $row['payment_status'],
                    'payment_method' => $row['payment_method'],
                    'amount_paid' => $row['amount_paid'],
                    'paid_at' => $paidAt,
                    'notes' => 'Demo completed visit',
                ],
            );
        }
    }
}
