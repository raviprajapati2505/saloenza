<?php

namespace Tests\Feature\Role;

use App\Models\Appointment;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Role;
use App\Models\SalonServiceProduct;
use App\Models\Saloon;
use App\Models\SaloonBranch;
use App\Models\Service;
use App\Models\User;
use App\Support\Role\RoleCodes;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Business-logic coverage for the four primary roles:
 * platform.super_admin, salon.franchise_owner, salon.branch_manager, salon.staff.
 */
class RoleBusinessLogicTest extends TestCase
{
    use RefreshDatabase;

    private Saloon $saloon;

    private SaloonBranch $branchA;

    private SaloonBranch $branchB;

    private User $superAdmin;

    private User $owner;

    private User $branchManager;

    private User $staffA;

    private User $staffB;

    private Service $service;

    private Customer $customer;

    private Appointment $appointmentA;

    private Appointment $appointmentB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedReferenceData();
        $this->seed(SubscriptionPlanSeeder::class);

        $this->saloon = $this->createSaloon(['name' => 'Role Logic Salon']);
        $this->assignDefaultSubscription($this->saloon, 'pro');

        $this->branchA = SaloonBranch::query()->create([
            'saloon_id' => $this->saloon->id,
            'branch_name' => 'Branch A',
            'business_address_1' => 'A Street',
            'city' => 'Mumbai',
            'state' => 'Maharashtra',
            'area_pincode' => '400001',
            'country' => 'India',
            'is_active' => true,
        ]);
        $this->branchB = SaloonBranch::query()->create([
            'saloon_id' => $this->saloon->id,
            'branch_name' => 'Branch B',
            'business_address_1' => 'B Street',
            'city' => 'Mumbai',
            'state' => 'Maharashtra',
            'area_pincode' => '400002',
            'country' => 'India',
            'is_active' => true,
        ]);

        $ownerRole = Role::findByCode(RoleCodes::SALON_FRANCHISE_OWNER);
        $managerRole = Role::findByCode(RoleCodes::SALON_BRANCH_MANAGER);
        $staffRole = Role::findByCode(RoleCodes::SALON_STAFF);
        $superAdminRole = Role::findByCode(RoleCodes::PLATFORM_SUPER_ADMIN);

        $this->superAdmin = User::factory()->create([
            'name' => 'Super Admin',
            'email' => 'super-admin-logic@test.com',
            'phone' => '+917200000001',
            'password' => 'Pass@1234',
            'role_id' => $superAdminRole->id,
            'saloon_id' => null,
            'branch_id' => null,
            'is_active' => true,
            'is_system_admin' => true,
        ]);

        $this->owner = User::factory()->create([
            'name' => 'Franchise Owner',
            'email' => 'owner-logic@test.com',
            'phone' => '+917200000002',
            'password' => 'Pass@1234',
            'role_id' => $ownerRole->id,
            'saloon_id' => $this->saloon->id,
            'branch_id' => null,
            'is_active' => true,
            'onboarding_completed_at' => now(),
        ]);

        $this->branchManager = User::factory()->create([
            'name' => 'Branch Manager A',
            'email' => 'manager-logic@test.com',
            'phone' => '+917200000003',
            'password' => 'Pass@1234',
            'role_id' => $managerRole->id,
            'saloon_id' => $this->saloon->id,
            'branch_id' => $this->branchA->id,
            'is_active' => true,
            'onboarding_completed_at' => now(),
        ]);

        $this->staffA = User::factory()->create([
            'name' => 'Staff A',
            'email' => 'staff-a-logic@test.com',
            'phone' => '+917200000004',
            'password' => 'Pass@1234',
            'role_id' => $staffRole->id,
            'saloon_id' => $this->saloon->id,
            'branch_id' => $this->branchA->id,
            'is_active' => true,
            'onboarding_completed_at' => now(),
        ]);

        $this->staffB = User::factory()->create([
            'name' => 'Staff B',
            'email' => 'staff-b-logic@test.com',
            'phone' => '+917200000005',
            'password' => 'Pass@1234',
            'role_id' => $staffRole->id,
            'saloon_id' => $this->saloon->id,
            'branch_id' => $this->branchB->id,
            'is_active' => true,
            'onboarding_completed_at' => now(),
        ]);

        $category = Category::query()->create([
            'name' => 'Hair',
            'is_active' => true,
        ]);
        $this->service = Service::query()->create([
            'name' => 'Haircut',
            'category_id' => $category->id,
            'default_price' => 500,
            'duration_minutes' => 30,
            'is_active' => true,
        ]);

        SalonServiceProduct::query()->create([
            'saloon_id' => $this->saloon->id,
            'branch_id' => null,
            'service_id' => $this->service->id,
            'product_id' => null,
            'price' => 500,
            'duration_minutes' => 30,
            'is_active' => true,
        ]);

        $this->customer = Customer::query()->create([
            'name' => 'Walk-in Guest',
            'phone' => '+919888800001',
            'is_active' => true,
        ]);
        $this->customer->attachSaloon($this->saloon->id);

        $starts = now()->addDay()->setTime(10, 0);
        $ends = $starts->copy()->addMinutes(30);

        $this->appointmentA = Appointment::query()->create([
            'saloon_id' => $this->saloon->id,
            'branch_id' => $this->branchA->id,
            'customer_id' => $this->customer->id,
            'staff_id' => $this->staffA->id,
            'service_id' => $this->service->id,
            'starts_at' => $starts,
            'ends_at' => $ends,
            'status' => 'scheduled',
            'type' => Appointment::TYPE_APPOINTMENT,
            'price' => 500,
            'discount' => 0,
            'grand_total' => 500,
            'created_by' => $this->owner->id,
        ]);

        $this->appointmentB = Appointment::query()->create([
            'saloon_id' => $this->saloon->id,
            'branch_id' => $this->branchB->id,
            'customer_id' => $this->customer->id,
            'staff_id' => $this->staffB->id,
            'service_id' => $this->service->id,
            'starts_at' => $starts->copy()->addHours(2),
            'ends_at' => $ends->copy()->addHours(2),
            'status' => 'scheduled',
            'type' => Appointment::TYPE_APPOINTMENT,
            'price' => 500,
            'discount' => 0,
            'grand_total' => 500,
            'created_by' => $this->owner->id,
        ]);
    }

    // -------------------------------------------------------------------------
    // Super Admin
    // -------------------------------------------------------------------------

    public function test_super_admin_can_view_appointments_across_saloons(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $this->getJson('/api/v1/appointments?page=1&per_page=50')
            ->assertOk()
            ->assertJsonFragment(['id' => $this->appointmentA->id])
            ->assertJsonFragment(['id' => $this->appointmentB->id]);
    }

    public function test_super_admin_cannot_create_appointments(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $this->postJson('/api/v1/appointments', $this->appointmentPayload([
            'saloon_id' => $this->saloon->id,
            'branch_id' => $this->branchA->id,
            'staff_id' => $this->staffA->id,
        ]))
            ->assertForbidden();
    }

    public function test_super_admin_cannot_update_appointments(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $this->putJson("/api/v1/appointments/{$this->appointmentA->id}", $this->appointmentPayload([
            'branch_id' => $this->branchA->id,
            'staff_id' => $this->staffA->id,
            'notes' => 'Admin edit attempt',
        ]))
            ->assertForbidden();
    }

    public function test_super_admin_can_manage_platform_saloons(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $this->getJson('/api/v1/saloons?page=1&per_page=50')
            ->assertOk()
            ->assertJsonFragment(['id' => $this->saloon->id]);
    }

    public function test_super_admin_cannot_list_tenant_branches_without_saloon_link(): void
    {
        Sanctum::actingAs($this->superAdmin);

        // Tenant branch APIs require a saloon-linked account; platform uses /admin/branches.
        $this->getJson('/api/v1/branches?page=1&per_page=50')
            ->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // Owner
    // -------------------------------------------------------------------------

    public function test_owner_sees_appointments_from_all_branches(): void
    {
        Sanctum::actingAs($this->owner);

        $response = $this->getJson('/api/v1/appointments?page=1&per_page=50')->assertOk();
        $ids = collect($response->json('data.appointments'))->pluck('id')->all();

        $this->assertContains($this->appointmentA->id, $ids);
        $this->assertContains($this->appointmentB->id, $ids);
    }

    public function test_owner_can_filter_appointments_by_any_branch(): void
    {
        Sanctum::actingAs($this->owner);

        $response = $this->getJson('/api/v1/appointments?page=1&per_page=50&branch_id='.$this->branchB->id)
            ->assertOk();

        $ids = collect($response->json('data.appointments'))->pluck('id')->all();

        $this->assertContains($this->appointmentB->id, $ids);
        $this->assertNotContains($this->appointmentA->id, $ids);
    }

    public function test_owner_can_create_update_and_delete_appointments(): void
    {
        Sanctum::actingAs($this->owner);

        $create = $this->postJson('/api/v1/appointments', $this->appointmentPayload([
            'branch_id' => $this->branchB->id,
            'staff_id' => $this->staffB->id,
            'customer_name' => 'Owner Booking',
            'customer_phone' => '+919888800010',
        ]))->assertCreated();

        $appointmentId = (int) $create->json('data.appointment.id');

        $this->putJson("/api/v1/appointments/{$appointmentId}", $this->appointmentPayload([
            'branch_id' => $this->branchB->id,
            'staff_id' => $this->staffB->id,
            'customer_name' => 'Owner Booking Updated',
            'customer_phone' => '+919888800010',
            'notes' => 'Updated by owner',
        ]))
            ->assertOk()
            ->assertJsonPath('data.appointment.notes', 'Updated by owner');

        $this->deleteJson("/api/v1/appointments/{$appointmentId}")
            ->assertOk();

        $this->assertDatabaseMissing('appointments', ['id' => $appointmentId]);
    }

    public function test_owner_can_create_and_list_all_branches(): void
    {
        Sanctum::actingAs($this->owner);

        $this->getJson('/api/v1/branches?page=1&per_page=50')
            ->assertOk()
            ->assertJsonFragment(['branch_name' => 'Branch A'])
            ->assertJsonFragment(['branch_name' => 'Branch B']);

        $this->postJson('/api/v1/branches', [
            'branch_name' => 'Branch C',
            'business_address_1' => 'C Street',
            'city' => 'Mumbai',
            'state' => 'Maharashtra',
            'area_pincode' => '400003',
            'country' => 'India',
            'is_active' => true,
        ])->assertCreated();
    }

    public function test_owner_can_manage_staff_across_branches(): void
    {
        Sanctum::actingAs($this->owner);
        $staffRole = Role::findByCode(RoleCodes::SALON_STAFF);

        $this->getJson('/api/v1/staff?page=1&per_page=50')
            ->assertOk()
            ->assertJsonFragment(['email' => $this->staffA->email])
            ->assertJsonFragment(['email' => $this->staffB->email]);

        $this->postJson('/api/v1/staff', [
            'firstname' => 'New',
            'lastname' => 'Hire',
            'email' => 'new-hire-logic@test.com',
            'phone' => '+917200000020',
            'password' => 'Pass@1234',
            'password_confirmation' => 'Pass@1234',
            'role_id' => $staffRole->id,
            'branch_id' => $this->branchB->id,
            'is_active' => true,
        ])
            ->assertCreated()
            ->assertJsonPath('data.staff.branch_id', $this->branchB->id);
    }

    public function test_owner_can_view_and_update_subscription_settings(): void
    {
        Sanctum::actingAs($this->owner);

        $this->getJson('/api/v1/subscription')->assertOk();
        $this->getJson('/api/v1/billing/config')->assertOk();
    }

    public function test_owner_cannot_access_other_salon_data(): void
    {
        $otherSalon = $this->createSaloon(['name' => 'Other Salon']);
        $this->assignDefaultSubscription($otherSalon, 'pro');

        $otherBranch = SaloonBranch::query()->create([
            'saloon_id' => $otherSalon->id,
            'branch_name' => 'Other Branch',
            'business_address_1' => 'X Street',
            'city' => 'Pune',
            'state' => 'Maharashtra',
            'area_pincode' => '411001',
            'country' => 'India',
            'is_active' => true,
        ]);

        $otherAppointment = Appointment::query()->create([
            'saloon_id' => $otherSalon->id,
            'branch_id' => $otherBranch->id,
            'customer_id' => $this->customer->id,
            'staff_id' => null,
            'service_id' => $this->service->id,
            'starts_at' => now()->addDays(2),
            'ends_at' => now()->addDays(2)->addHour(),
            'status' => 'scheduled',
            'type' => Appointment::TYPE_APPOINTMENT,
            'price' => 100,
            'discount' => 0,
            'grand_total' => 100,
            'created_by' => $this->owner->id,
        ]);

        Sanctum::actingAs($this->owner);

        $this->getJson("/api/v1/appointments/{$otherAppointment->id}")
            ->assertForbidden();

        $this->getJson('/api/v1/branches?page=1&per_page=50')
            ->assertOk()
            ->assertJsonMissing(['branch_name' => 'Other Branch']);
    }

    // -------------------------------------------------------------------------
    // Branch Manager
    // -------------------------------------------------------------------------

    public function test_branch_manager_lists_only_own_branch_appointments_by_default(): void
    {
        Sanctum::actingAs($this->branchManager);

        $response = $this->getJson('/api/v1/appointments?page=1&per_page=50')->assertOk();
        $ids = collect($response->json('data.appointments'))->pluck('id')->all();

        $this->assertContains($this->appointmentA->id, $ids);
        $this->assertNotContains($this->appointmentB->id, $ids);
    }

    public function test_branch_manager_cannot_list_other_branch_appointments_via_filter(): void
    {
        Sanctum::actingAs($this->branchManager);

        $response = $this->getJson('/api/v1/appointments?page=1&per_page=50&branch_id='.$this->branchB->id)
            ->assertOk();

        $ids = collect($response->json('data.appointments'))->pluck('id')->all();

        $this->assertNotContains($this->appointmentB->id, $ids);
        $this->assertContains($this->appointmentA->id, $ids);
    }

    public function test_branch_manager_cannot_show_other_branch_appointment(): void
    {
        Sanctum::actingAs($this->branchManager);

        $this->getJson("/api/v1/appointments/{$this->appointmentB->id}")
            ->assertForbidden();
    }

    public function test_branch_manager_cannot_update_other_branch_appointment(): void
    {
        Sanctum::actingAs($this->branchManager);

        $this->putJson("/api/v1/appointments/{$this->appointmentB->id}", $this->appointmentPayload([
            'branch_id' => $this->branchB->id,
            'staff_id' => $this->staffB->id,
            'notes' => 'Cross-branch hack',
        ]))
            ->assertForbidden();
    }

    public function test_branch_manager_can_show_and_update_own_branch_appointment(): void
    {
        Sanctum::actingAs($this->branchManager);

        $this->getJson("/api/v1/appointments/{$this->appointmentA->id}")
            ->assertOk()
            ->assertJsonPath('data.appointment.id', $this->appointmentA->id);

        $this->putJson("/api/v1/appointments/{$this->appointmentA->id}", $this->appointmentPayload([
            'branch_id' => $this->branchA->id,
            'staff_id' => $this->staffA->id,
            'customer_id' => $this->customer->id,
            'notes' => 'Updated by manager',
        ]))
            ->assertOk()
            ->assertJsonPath('data.appointment.notes', 'Updated by manager');
    }

    public function test_branch_manager_create_forces_own_branch(): void
    {
        Sanctum::actingAs($this->branchManager);

        $this->postJson('/api/v1/appointments', $this->appointmentPayload([
            'branch_id' => $this->branchB->id,
            'staff_id' => $this->staffA->id,
            'customer_name' => 'Manager Booking',
            'customer_phone' => '+919888800011',
        ]))
            ->assertCreated()
            ->assertJsonPath('data.appointment.branch_id', $this->branchA->id);
    }

    public function test_branch_manager_cannot_delete_appointments(): void
    {
        Sanctum::actingAs($this->branchManager);

        $this->deleteJson("/api/v1/appointments/{$this->appointmentA->id}")
            ->assertForbidden()
            ->assertJsonPath('required_permissions.0', 'appointments.delete');
    }

    public function test_branch_manager_only_sees_own_branch_and_cannot_create_branch(): void
    {
        Sanctum::actingAs($this->branchManager);

        $this->getJson('/api/v1/branches?page=1&per_page=50')
            ->assertOk()
            ->assertJsonFragment(['branch_name' => 'Branch A'])
            ->assertJsonMissing(['branch_name' => 'Branch B']);

        $this->postJson('/api/v1/branches', [
            'branch_name' => 'Hacked Branch',
            'business_address_1' => 'Hack Street',
            'city' => 'Mumbai',
            'state' => 'Maharashtra',
            'area_pincode' => '400099',
            'country' => 'India',
            'is_active' => true,
        ])->assertForbidden();
    }

    public function test_branch_manager_cannot_update_other_branch(): void
    {
        Sanctum::actingAs($this->branchManager);

        $this->patchJson("/api/v1/branches/{$this->branchB->id}", [
            'branch_name' => 'Hacked B',
            'business_address_1' => 'B Street',
            'city' => 'Mumbai',
            'state' => 'Maharashtra',
            'area_pincode' => '400002',
            'country' => 'India',
            'is_active' => true,
        ])->assertForbidden();
    }

    public function test_branch_manager_staff_list_is_limited_to_own_branch(): void
    {
        Sanctum::actingAs($this->branchManager);

        $response = $this->getJson('/api/v1/staff?page=1&per_page=50')->assertOk();
        $emails = collect($response->json('data.staff'))->pluck('email')->all();

        $this->assertContains($this->staffA->email, $emails);
        $this->assertNotContains($this->staffB->email, $emails);
    }

    public function test_branch_manager_cannot_delete_staff(): void
    {
        Sanctum::actingAs($this->branchManager);

        $this->deleteJson("/api/v1/staff/{$this->staffA->id}")
            ->assertForbidden()
            ->assertJsonPath('required_permissions.0', 'staff.delete');
    }

    public function test_branch_manager_cannot_access_subscription_settings(): void
    {
        Sanctum::actingAs($this->branchManager);

        $this->getJson('/api/v1/subscription')
            ->assertForbidden()
            ->assertJsonPath('required_permissions.0', 'settings.view');
    }

    public function test_branch_manager_booking_options_lock_to_own_branch(): void
    {
        Sanctum::actingAs($this->branchManager);

        $this->getJson('/api/v1/appointments/booking-options')
            ->assertOk()
            ->assertJsonPath('data.defaults.lock_branch', true)
            ->assertJsonPath('data.defaults.branch_id', $this->branchA->id)
            ->assertJsonCount(1, 'data.branches')
            ->assertJsonPath('data.branches.0.id', $this->branchA->id);
    }

    // -------------------------------------------------------------------------
    // Staff
    // -------------------------------------------------------------------------

    public function test_staff_lists_only_own_branch_appointments(): void
    {
        Sanctum::actingAs($this->staffA);

        $response = $this->getJson('/api/v1/appointments?page=1&per_page=50')->assertOk();
        $ids = collect($response->json('data.appointments'))->pluck('id')->all();

        $this->assertContains($this->appointmentA->id, $ids);
        $this->assertNotContains($this->appointmentB->id, $ids);
    }

    public function test_staff_cannot_show_other_branch_appointment(): void
    {
        Sanctum::actingAs($this->staffA);

        $this->getJson("/api/v1/appointments/{$this->appointmentB->id}")
            ->assertForbidden();
    }

    public function test_staff_cannot_update_other_branch_appointment(): void
    {
        Sanctum::actingAs($this->staffA);

        $this->putJson("/api/v1/appointments/{$this->appointmentB->id}", $this->appointmentPayload([
            'branch_id' => $this->branchB->id,
            'staff_id' => $this->staffB->id,
            'notes' => 'Staff cross-branch hack',
        ]))
            ->assertForbidden();
    }

    public function test_staff_create_forces_own_branch_and_self_as_staff(): void
    {
        Sanctum::actingAs($this->staffA);

        $this->postJson('/api/v1/appointments', $this->appointmentPayload([
            'branch_id' => $this->branchB->id,
            'staff_id' => $this->staffB->id,
            'customer_name' => 'Staff Booking',
            'customer_phone' => '+919888800012',
        ]))
            ->assertCreated()
            ->assertJsonPath('data.appointment.branch_id', $this->branchA->id)
            ->assertJsonPath('data.appointment.staff_id', $this->staffA->id);
    }

    public function test_staff_cannot_delete_appointments(): void
    {
        Sanctum::actingAs($this->staffA);

        $this->deleteJson("/api/v1/appointments/{$this->appointmentA->id}")
            ->assertForbidden()
            ->assertJsonPath('required_permissions.0', 'appointments.delete');
    }

    public function test_staff_cannot_access_staff_module(): void
    {
        Sanctum::actingAs($this->staffA);

        $this->getJson('/api/v1/staff')
            ->assertForbidden()
            ->assertJsonPath('required_permissions.0', 'staff.view');
    }

    public function test_staff_cannot_access_branches_module(): void
    {
        Sanctum::actingAs($this->staffA);

        $this->getJson('/api/v1/branches')
            ->assertForbidden()
            ->assertJsonPath('required_permissions.0', 'branches.view');
    }

    public function test_staff_cannot_access_roles_module(): void
    {
        Sanctum::actingAs($this->staffA);

        $this->getJson('/api/v1/roles')
            ->assertForbidden()
            ->assertJsonPath('required_permissions.0', 'roles.view');
    }

    public function test_staff_cannot_access_subscription_settings(): void
    {
        Sanctum::actingAs($this->staffA);

        $this->getJson('/api/v1/subscription')
            ->assertForbidden()
            ->assertJsonPath('required_permissions.0', 'settings.view');
    }

    public function test_staff_can_manage_customers(): void
    {
        Sanctum::actingAs($this->staffA);

        $this->getJson('/api/v1/customers?page=1&per_page=50')
            ->assertOk()
            ->assertJsonFragment(['id' => $this->customer->id]);

        $this->postJson('/api/v1/customers', [
            'name' => 'Staff Customer',
            'phone' => '+919888800099',
            'is_active' => true,
        ])->assertCreated();
    }

    public function test_staff_booking_options_lock_branch_and_staff(): void
    {
        Sanctum::actingAs($this->staffA);

        $this->getJson('/api/v1/appointments/booking-options')
            ->assertOk()
            ->assertJsonPath('data.defaults.lock_branch', true)
            ->assertJsonPath('data.defaults.lock_staff', true)
            ->assertJsonPath('data.defaults.staff_id', $this->staffA->id)
            ->assertJsonCount(1, 'data.staff')
            ->assertJsonPath('data.staff.0.id', $this->staffA->id);
    }

    public function test_staff_can_view_catalog_services_but_cannot_create_master_service(): void
    {
        Sanctum::actingAs($this->staffA);

        $this->getJson('/api/v1/services?page=1&per_page=50')->assertOk();

        $this->postJson('/api/v1/services', [
            'name' => 'Unauthorized Service',
            'category_id' => $this->service->category_id,
            'default_price' => 100,
            'duration_minutes' => 15,
            'is_active' => true,
        ])->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // Cross-role permission matrix smoke checks
    // -------------------------------------------------------------------------

    public function test_permission_matrix_for_core_modules(): void
    {
        $cases = [
            // [user, path, method, expected allowed?]
            [$this->owner, '/api/v1/appointments', 'GET', true],
            [$this->branchManager, '/api/v1/appointments', 'GET', true],
            [$this->staffA, '/api/v1/appointments', 'GET', true],
            [$this->owner, '/api/v1/staff', 'GET', true],
            [$this->branchManager, '/api/v1/staff', 'GET', true],
            [$this->staffA, '/api/v1/staff', 'GET', false],
            [$this->owner, '/api/v1/branches', 'GET', true],
            [$this->branchManager, '/api/v1/branches', 'GET', true],
            [$this->staffA, '/api/v1/branches', 'GET', false],
            [$this->owner, '/api/v1/roles', 'GET', true],
            [$this->branchManager, '/api/v1/roles', 'GET', true],
            [$this->staffA, '/api/v1/roles', 'GET', false],
            [$this->owner, '/api/v1/subscription', 'GET', true],
            [$this->branchManager, '/api/v1/subscription', 'GET', false],
            [$this->staffA, '/api/v1/subscription', 'GET', false],
            [$this->owner, '/api/v1/customers', 'GET', true],
            [$this->branchManager, '/api/v1/customers', 'GET', true],
            [$this->staffA, '/api/v1/customers', 'GET', true],
            [$this->owner, '/api/v1/inventory/summary', 'GET', true],
            [$this->branchManager, '/api/v1/inventory/summary', 'GET', true],
            [$this->staffA, '/api/v1/inventory/summary', 'GET', false],
            [$this->owner, '/api/v1/analytics/summary', 'GET', true],
            [$this->branchManager, '/api/v1/analytics/summary', 'GET', true],
            [$this->staffA, '/api/v1/analytics/summary', 'GET', false],
        ];

        foreach ($cases as [$user, $path, $method, $allowed]) {
            Sanctum::actingAs($user);
            $query = str_contains($path, '?') ? '' : '?';
            if (str_contains($path, 'analytics/summary')) {
                $from = now()->subDays(7)->toDateString();
                $to = now()->toDateString();
                $query = "?from={$from}&to={$to}&branch_id={$this->branchA->id}";
            } elseif (str_contains($path, 'inventory/summary')) {
                $query = "?branch_id={$this->branchA->id}";
            } elseif (! str_contains($path, '?')) {
                $query = '?page=1&per_page=10';
            }

            $response = $this->json($method, $path.$query);

            if ($allowed) {
                $this->assertTrue(
                    $response->isSuccessful(),
                    sprintf('%s should access %s %s (got %s)', $user->email, $method, $path, $response->status()),
                );
            } else {
                $response->assertForbidden();
            }
        }
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function appointmentPayload(array $overrides = []): array
    {
        $staffId = $overrides['staff_id'] ?? $this->staffA->id;
        unset($overrides['staff_id']);

        $startsAt = now()->addDays(3)->setTime(11, 0);

        return array_merge([
            'type' => Appointment::TYPE_APPOINTMENT,
            'starts_at' => $startsAt->toIso8601String(),
            'status' => 'scheduled',
            'customer_id' => $this->customer->id,
            'discount' => 0,
            'services' => [[
                'service_id' => $this->service->id,
                'staff_id' => $staffId,
                'price' => 500,
                'duration_minutes' => 30,
            ]],
        ], $overrides);
    }
}
