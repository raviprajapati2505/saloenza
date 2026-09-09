<?php

namespace Tests\Feature\Staff;

use App\Support\Role\RoleCodes;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Saloon;
use App\Models\SaloonBranch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StaffApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedReferenceData();
    }

    public function test_system_admin_can_manage_staff_across_saloons(): void
    {
        $saloonA = Saloon::query()->create(['name' => 'Saloon A']);
        $saloonB = Saloon::query()->create(['name' => 'Saloon B']);
        $this->assignDefaultSubscription($saloonA);
        $this->assignDefaultSubscription($saloonB);

        $branchA = SaloonBranch::query()->create([
            'saloon_id' => $saloonA->id,
            'branch_name' => 'Branch A',
            'business_address_1' => 'Address A',
            'city' => 'Mumbai',
            'state' => 'MH',
            'area_pincode' => '400001',
        ]);

        $role = $this->createTenantRole([
            'name' => 'Stylist',
        ]);

        $admin = $this->createSystemAdmin([
            'phone' => '+917888888881',
        ]);

        Sanctum::actingAs($admin);

        $createResponse = $this->postJson('/api/v1/staff', [
            'firstname' => 'Jane',
            'lastname' => 'Doe',
            'email' => 'jane.doe@example.com',
            'phone' => '+917888888882',
            'password' => 'Pass@1234',
            'password_confirmation' => 'Pass@1234',
            'is_active' => true,
            'role_id' => $role->id,
            'saloon_id' => $saloonA->id,
            'branch_id' => $branchA->id,
            'commission_rate' => 12.5,
            'per_month_salary' => 25000,
            'notes' => 'Senior stylist',
        ]);

        $createResponse
            ->assertCreated()
            ->assertJsonPath('data.staff.firstname', 'Jane')
            ->assertJsonPath('data.staff.saloon_id', $saloonA->id)
            ->assertJsonPath('data.staff.commission_rate', 12.5)
            ->assertJsonPath('data.staff.per_month_salary', 25000)
            ->assertJsonPath('data.staff.notes', 'Senior stylist');

        $staffId = (int) $createResponse->json('data.staff.id');

        User::factory()->create([
            'firstname' => 'Other',
            'lastname' => 'Saloon',
            'email' => 'other.saloon@example.com',
            'phone' => '+917888888883',
            'saloon_id' => $saloonB->id,
            'role_id' => $role->id,
            'is_system_admin' => false,
        ]);

        $totalStaff = User::where('is_system_admin', false)->count();
        $this->getJson('/api/v1/staff?page=1&per_page=10')
            ->assertOk()
            ->assertJsonCount(min(10, $totalStaff), 'data.staff');

        $this->getJson('/api/v1/staff?page=1&per_page=10&saloon_id='.$saloonA->id)
            ->assertOk()
            ->assertJsonCount(1, 'data.staff')
            ->assertJsonPath('data.staff.0.email', 'jane.doe@example.com');

        $this->getJson("/api/v1/staff/{$staffId}")
            ->assertOk()
            ->assertJsonPath('data.staff.email', 'jane.doe@example.com');

        $this->putJson("/api/v1/staff/{$staffId}", [
            'firstname' => 'Janet',
            'lastname' => 'Doe',
            'email' => 'jane.doe@example.com',
            'phone' => '+917888888882',
            'is_active' => false,
            'role_id' => $role->id,
            'saloon_id' => $saloonA->id,
            'branch_id' => $branchA->id,
        ])->assertOk()
            ->assertJsonPath('data.staff.firstname', 'Janet')
            ->assertJsonPath('data.staff.is_active', false);

        $this->deleteJson("/api/v1/staff/{$staffId}")
            ->assertOk()
            ->assertJsonPath('message', 'Staff deleted successfully.');

        $this->assertDatabaseMissing('users', [
            'id' => $staffId,
        ]);
    }

    public function test_salon_user_can_only_manage_staff_within_current_saloon(): void
    {
        $saloonA = Saloon::query()->create(['name' => 'Saloon A']);
        $saloonB = Saloon::query()->create(['name' => 'Saloon B']);
        $this->assignDefaultSubscription($saloonA);

        $branchA = SaloonBranch::query()->create([
            'saloon_id' => $saloonA->id,
            'branch_name' => 'Branch A',
            'business_address_1' => 'Address A',
            'city' => 'Mumbai',
            'state' => 'MH',
            'area_pincode' => '400001',
        ]);

        $role = $this->createTenantRole([
            'name' => 'Manager',
            'saloon_id' => $saloonA->id,
        ]);

        $role->permissions()->sync(
            Permission::query()->whereIn('code', [
                'staff.view',
                'staff.create',
                'staff.update',
                'staff.delete',
            ])->pluck('id'),
        );

        $manager = User::factory()->create([
            'firstname' => 'Salon',
            'lastname' => 'Manager',
            'email' => 'manager@example.com',
            'phone' => '+917888888884',
            'saloon_id' => $saloonA->id,
            'branch_id' => $branchA->id,
            'role_id' => $role->id,
            'is_system_admin' => false,
        ]);

        $sameSaloonStaff = User::factory()->create([
            'firstname' => 'Same',
            'lastname' => 'Saloon',
            'email' => 'same.saloon@example.com',
            'phone' => '+917888888885',
            'saloon_id' => $saloonA->id,
            'role_id' => $role->id,
            'is_system_admin' => false,
        ]);

        $otherSaloonStaff = User::factory()->create([
            'firstname' => 'Other',
            'lastname' => 'Saloon',
            'email' => 'other.saloon@example.com',
            'phone' => '+917888888886',
            'saloon_id' => $saloonB->id,
            'role_id' => $role->id,
            'is_system_admin' => false,
        ]);

        Sanctum::actingAs($manager);

        $this->getJson('/api/v1/staff?page=1&per_page=10')
            ->assertOk()
            ->assertJsonCount(2, 'data.staff');

        $this->getJson("/api/v1/staff/{$sameSaloonStaff->id}")
            ->assertOk()
            ->assertJsonPath('data.staff.email', 'same.saloon@example.com');

        $this->getJson("/api/v1/staff/{$otherSaloonStaff->id}")
            ->assertForbidden();

        $this->postJson('/api/v1/staff', [
            'firstname' => 'New',
            'lastname' => 'Staff',
            'email' => 'new.staff@example.com',
            'phone' => '+917888888887',
            'password' => 'Pass@1234',
            'password_confirmation' => 'Pass@1234',
            'is_active' => true,
            'role_id' => $role->id,
            'branch_id' => $branchA->id,
        ])->assertCreated()
            ->assertJsonPath('data.staff.saloon_id', $saloonA->id);

        $this->putJson("/api/v1/staff/{$otherSaloonStaff->id}", [
            'firstname' => 'Blocked',
            'lastname' => 'Update',
            'email' => 'other.saloon@example.com',
            'phone' => '+917888888886',
            'is_active' => true,
            'role_id' => $role->id,
        ])->assertForbidden();

        $this->deleteJson("/api/v1/staff/{$otherSaloonStaff->id}")
            ->assertForbidden();
    }

    public function test_user_without_staff_view_permission_cannot_list_staff(): void
    {
        $saloon = Saloon::query()->create(['name' => 'Saloon A']);
        $this->assignDefaultSubscription($saloon);
        $role = $this->createTenantRole([
            'name' => 'Limited',
            'saloon_id' => $saloon->id,
        ]);

        $user = User::factory()->create([
            'phone' => '+917888888888',
            'saloon_id' => $saloon->id,
            'role_id' => $role->id,
            'is_system_admin' => false,
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/staff?page=1&per_page=10')
            ->assertForbidden();
    }

    public function test_authenticated_user_can_search_and_paginate_staff(): void
    {
        $saloon = Saloon::query()->create(['name' => 'Search Saloon']);
        $this->assignDefaultSubscription($saloon);
        $role = $this->createTenantRole([
            'name' => 'Stylist',
            'saloon_id' => $saloon->id,
        ]);

        User::factory()->create([
            'firstname' => 'Searchable',
            'lastname' => 'Staff',
            'name' => 'Searchable Staff',
            'email' => 'searchable@example.com',
            'phone' => '+917888888889',
            'saloon_id' => $saloon->id,
            'role_id' => $role->id,
            'is_active' => true,
            'is_system_admin' => false,
        ]);

        User::factory()->create([
            'firstname' => 'Hidden',
            'lastname' => 'Staff',
            'name' => 'Hidden Staff',
            'email' => 'hidden@example.com',
            'phone' => '+917888888890',
            'saloon_id' => $saloon->id,
            'role_id' => $role->id,
            'is_active' => false,
            'is_system_admin' => false,
        ]);

        $admin = $this->createSystemAdmin([
            'phone' => '+917888888891',
        ]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/staff?page=1&per_page=10&search=Searchable')
            ->assertOk()
            ->assertJsonCount(1, 'data.staff')
            ->assertJsonPath('data.staff.0.firstname', 'Searchable');

        $this->getJson('/api/v1/staff?page=1&per_page=10&is_active=0')
            ->assertOk()
            ->assertJsonCount(1, 'data.staff')
            ->assertJsonPath('data.staff.0.firstname', 'Hidden');
    }

    public function test_basic_plan_can_fetch_assignable_roles_without_roles_module(): void
    {
        $this->seed(\Database\Seeders\RoleSeeder::class);

        $saloon = Saloon::query()->create(['name' => 'Basic Saloon']);
        $this->assignDefaultSubscription($saloon, 'basic');

        $role = $this->createTenantRole([
            'name' => 'Stylist',
            'saloon_id' => $saloon->id,
        ]);

        $ownerRole = Role::findByCode(RoleCodes::SALON_FRANCHISE_OWNER)
            ?? $this->fail('Missing salon franchise owner role.');

        $owner = User::factory()->create([
            'phone' => '+917888888892',
            'saloon_id' => $saloon->id,
            'role_id' => $ownerRole->id,
            'is_active' => true,
            'is_system_admin' => false,
        ]);

        Sanctum::actingAs($owner);

        $this->getJson('/api/v1/roles?page=1&per_page=100')
            ->assertForbidden()
            ->assertJsonPath('required_modules', ['roles']);

        $this->getJson('/api/v1/staff/assignable-roles')
            ->assertOk()
            ->assertJsonFragment(['name' => $role->name]);
    }
}
