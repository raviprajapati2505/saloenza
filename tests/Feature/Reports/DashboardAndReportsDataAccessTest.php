<?php

namespace Tests\Feature\Reports;

use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Role;
use App\Models\SaloonBranch;
use App\Models\Service;
use App\Models\Category;
use App\Models\User;
use App\Support\Role\RoleCodes;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Dashboard/reports data sources used by FE aggregators (role-scoped APIs).
 */
class DashboardAndReportsDataAccessTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $manager;

    private User $staff;

    private SaloonBranch $branchA;

    private SaloonBranch $branchB;

    private Appointment $appointmentA;

    private Appointment $appointmentB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedReferenceData();
        $this->seed(SubscriptionPlanSeeder::class);

        $this->owner = $this->createOwnerUser(['email' => 'dash.owner@test.com']);
        $this->assignDefaultSubscription($this->owner->saloon, 'pro');

        $this->branchA = SaloonBranch::query()->create([
            'saloon_id' => $this->owner->saloon_id,
            'branch_name' => 'Reports A',
            'business_address_1' => 'A',
            'city' => 'Mumbai',
            'state' => 'MH',
            'area_pincode' => '400001',
            'country' => 'India',
            'is_active' => true,
        ]);
        $this->branchB = SaloonBranch::query()->create([
            'saloon_id' => $this->owner->saloon_id,
            'branch_name' => 'Reports B',
            'business_address_1' => 'B',
            'city' => 'Mumbai',
            'state' => 'MH',
            'area_pincode' => '400002',
            'country' => 'India',
            'is_active' => true,
        ]);

        $managerRole = Role::findByCode(RoleCodes::SALON_BRANCH_MANAGER);
        $staffRole = Role::findByCode(RoleCodes::SALON_STAFF);

        $this->manager = User::factory()->create([
            'email' => 'dash.manager@test.com',
            'phone' => '+917410000001',
            'role_id' => $managerRole->id,
            'saloon_id' => $this->owner->saloon_id,
            'branch_id' => $this->branchA->id,
            'is_active' => true,
            'onboarding_completed_at' => now(),
        ]);

        $this->staff = User::factory()->create([
            'email' => 'dash.staff@test.com',
            'phone' => '+917410000002',
            'role_id' => $staffRole->id,
            'saloon_id' => $this->owner->saloon_id,
            'branch_id' => $this->branchA->id,
            'is_active' => true,
            'onboarding_completed_at' => now(),
        ]);

        $category = Category::query()->create(['name' => 'Dash Cat', 'is_active' => true]);
        $service = Service::query()->create([
            'name' => 'Dash Cut',
            'category_id' => $category->id,
            'default_price' => 300,
            'duration_minutes' => 30,
            'is_active' => true,
        ]);

        $customer = Customer::query()->create([
            'name' => 'Dash Customer',
            'phone' => '+919700000001',
            'is_active' => true,
        ]);
        $customer->attachSaloon($this->owner->saloon_id);

        $this->appointmentA = Appointment::query()->create([
            'saloon_id' => $this->owner->saloon_id,
            'branch_id' => $this->branchA->id,
            'customer_id' => $customer->id,
            'staff_id' => $this->staff->id,
            'service_id' => $service->id,
            'starts_at' => now()->subDay()->setTime(10, 0),
            'ends_at' => now()->subDay()->setTime(10, 30),
            'status' => 'completed',
            'type' => Appointment::TYPE_APPOINTMENT,
            'price' => 300,
            'discount' => 0,
            'grand_total' => 300,
            'created_by' => $this->owner->id,
        ]);

        $this->appointmentB = Appointment::query()->create([
            'saloon_id' => $this->owner->saloon_id,
            'branch_id' => $this->branchB->id,
            'customer_id' => $customer->id,
            'staff_id' => null,
            'service_id' => $service->id,
            'starts_at' => now()->subDay()->setTime(12, 0),
            'ends_at' => now()->subDay()->setTime(12, 30),
            'status' => 'completed',
            'type' => Appointment::TYPE_APPOINTMENT,
            'price' => 400,
            'discount' => 0,
            'grand_total' => 400,
            'created_by' => $this->owner->id,
        ]);
    }

    public function test_owner_can_load_all_report_source_endpoints_across_branches(): void
    {
        Sanctum::actingAs($this->owner);

        $appointments = $this->getJson('/api/v1/appointments?page=1&per_page=50')->assertOk();
        $ids = collect($appointments->json('data.appointments'))->pluck('id')->all();
        $this->assertContains($this->appointmentA->id, $ids);
        $this->assertContains($this->appointmentB->id, $ids);

        $this->getJson('/api/v1/customers?page=1&per_page=50')->assertOk();
        $this->getJson('/api/v1/staff?page=1&per_page=50')->assertOk();
        $this->getJson('/api/v1/branches?page=1&per_page=50')
            ->assertOk()
            ->assertJsonFragment(['branch_name' => 'Reports A'])
            ->assertJsonFragment(['branch_name' => 'Reports B']);

        $from = now()->subDays(7)->toDateString();
        $to = now()->toDateString();
        $this->getJson("/api/v1/analytics/summary?from={$from}&to={$to}")
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'summary' => [
                        'collected_revenue',
                        'outstanding_revenue',
                        'payment_methods',
                        'payment_status_counts',
                        'daily_collected',
                    ],
                ],
            ]);
    }

    public function test_branch_manager_report_sources_are_branch_scoped(): void
    {
        Sanctum::actingAs($this->manager);

        $appointments = $this->getJson('/api/v1/appointments?page=1&per_page=50')->assertOk();
        $ids = collect($appointments->json('data.appointments'))->pluck('id')->all();
        $this->assertContains($this->appointmentA->id, $ids);
        $this->assertNotContains($this->appointmentB->id, $ids);

        $staff = $this->getJson('/api/v1/staff?page=1&per_page=50')->assertOk();
        $emails = collect($staff->json('data.staff'))->pluck('email')->all();
        $this->assertContains($this->staff->email, $emails);

        $this->getJson('/api/v1/branches?page=1&per_page=50')
            ->assertOk()
            ->assertJsonFragment(['branch_name' => 'Reports A'])
            ->assertJsonMissing(['branch_name' => 'Reports B']);
    }

    public function test_staff_can_load_appointments_and_customers_but_not_staff_or_branches_for_dashboard(): void
    {
        Sanctum::actingAs($this->staff);

        $this->getJson('/api/v1/appointments?page=1&per_page=50')->assertOk();
        $this->getJson('/api/v1/customers?page=1&per_page=50')->assertOk();
        $this->getJson('/api/v1/staff')->assertForbidden();
        $this->getJson('/api/v1/branches')->assertForbidden();
    }

    public function test_platform_admin_can_load_admin_report_source_endpoints(): void
    {
        $this->actingAsSystemAdmin(['email' => 'dash.admin@test.com']);

        $this->getJson('/api/v1/admin/onboarding?page=1&per_page=10')->assertOk();
        $this->getJson('/api/v1/admin/affiliates?page=1&per_page=10')->assertOk();
        $this->getJson('/api/v1/admin/subscription-upgrades?page=1&per_page=10')->assertOk();
        $this->getJson('/api/v1/subscription-plans?page=1&per_page=10')->assertOk();
    }

    public function test_login_workspace_routing_signals_for_each_primary_role(): void
    {
        $cases = [
            [$this->owner, 'tenant'],
            [$this->manager, 'tenant'],
            [$this->staff, 'tenant'],
        ];

        foreach ($cases as [$user, $workspace]) {
            Sanctum::actingAs($user);
            $this->getJson('/api/v1/me')
                ->assertOk()
                ->assertJsonPath('data.workspace', $workspace);
        }

        $admin = $this->createSystemAdmin(['email' => 'dash.me.admin@test.com', 'password' => 'Pass@1234']);
        $this->postJson('/api/v1/public/login', [
            'login' => $admin->email,
            'password' => 'Pass@1234',
        ])
            ->assertOk()
            ->assertJsonPath('data.workspace', 'platform');

        $affiliate = $this->createAffiliatePartnerUser([
            'email' => 'dash.aff@test.com',
            'password' => 'Pass@1234',
        ]);
        $this->postJson('/api/v1/public/login', [
            'login' => $affiliate->email,
            'password' => 'Pass@1234',
        ])
            ->assertOk()
            ->assertJsonPath('data.workspace', 'affiliate');
    }
}
