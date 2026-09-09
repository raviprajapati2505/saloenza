<?php

namespace Tests\Feature\Branch;

use App\Models\Category;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\SalonServiceProduct;
use App\Models\SaloonBranch;
use App\Models\Service;
use App\Models\User;
use App\Support\Role\RoleCodes;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BranchScopeApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedReferenceData();
        $this->seed(SubscriptionPlanSeeder::class);
    }

    public function test_branch_manager_only_sees_and_updates_assigned_branch(): void
    {
        $owner = $this->createOwnerUser([
            'email' => 'owner-scope@test.com',
            'phone' => '+917100000301',
        ]);
        $this->assignDefaultSubscription($owner->saloon, 'pro');

        $andheri = SaloonBranch::query()->create([
            'saloon_id' => $owner->saloon_id,
            'branch_name' => 'Andheri',
            'business_address_1' => 'Andheri Road',
            'city' => 'Mumbai',
            'state' => 'Maharashtra',
            'area_pincode' => '400053',
            'country' => 'India',
            'is_active' => true,
        ]);
        $bandra = SaloonBranch::query()->create([
            'saloon_id' => $owner->saloon_id,
            'branch_name' => 'Bandra',
            'business_address_1' => 'Bandra Road',
            'city' => 'Mumbai',
            'state' => 'Maharashtra',
            'area_pincode' => '400050',
            'country' => 'India',
            'is_active' => true,
        ]);

        $managerRole = Role::findByCode(RoleCodes::SALON_BRANCH_MANAGER);
        $manager = User::query()->create([
            'name' => 'Branch Manager',
            'firstname' => 'Branch',
            'lastname' => 'Manager',
            'email' => 'manager-scope@test.com',
            'phone' => '+917100000302',
            'password' => 'Pass@1234',
            'role_id' => $managerRole->id,
            'saloon_id' => $owner->saloon_id,
            'branch_id' => $andheri->id,
            'is_active' => true,
            'onboarding_completed_at' => now(),
        ]);

        $this->actingAs($manager);

        $this->getJson('/api/v1/branches?page=1&per_page=50')
            ->assertOk()
            ->assertJsonFragment(['branch_name' => 'Andheri'])
            ->assertJsonMissing(['branch_name' => 'Bandra'])
            ->assertJsonMissingPath('data.limits')
            ->assertJsonPath('data.can_create_branch', false)
            ->assertJsonMissingPath('data.branches.0.users_count')
            ->assertJsonMissingPath('data.branches.0.catalog_items_count')
            ->assertJsonMissingPath('data.branches.0.manager');

        $this->patchJson("/api/v1/branches/{$bandra->id}", [
            'branch_name' => 'Hacked Bandra',
            'business_address_1' => 'Bandra Road',
            'city' => 'Mumbai',
            'state' => 'Maharashtra',
            'area_pincode' => '400050',
            'country' => 'India',
            'is_active' => true,
        ])->assertForbidden();

        $this->patchJson("/api/v1/branches/{$andheri->id}", [
            'branch_name' => 'Andheri Updated',
            'business_address_1' => 'Andheri Road',
            'city' => 'Mumbai',
            'state' => 'Maharashtra',
            'area_pincode' => '400053',
            'country' => 'India',
            'is_active' => false,
        ])->assertOk()
            ->assertJsonPath('data.branch.branch_name', 'Andheri Updated')
            ->assertJsonPath('data.branch.is_active', true)
            ->assertJsonMissingPath('data.branch.users_count')
            ->assertJsonMissingPath('data.branch.catalog_items_count')
            ->assertJsonMissingPath('data.branch.manager');

        $this->assertDatabaseHas('saloon_branches', [
            'id' => $andheri->id,
            'branch_name' => 'Andheri Updated',
            'is_active' => true,
        ]);
    }

    public function test_branch_manager_staff_create_is_forced_to_own_branch(): void
    {
        $owner = $this->createOwnerUser([
            'email' => 'owner-staff-scope@test.com',
            'phone' => '+917100000311',
        ]);
        $this->assignDefaultSubscription($owner->saloon, 'pro');

        $andheri = SaloonBranch::query()->create([
            'saloon_id' => $owner->saloon_id,
            'branch_name' => 'Andheri',
            'business_address_1' => 'Andheri Road',
            'city' => 'Mumbai',
            'state' => 'Maharashtra',
            'area_pincode' => '400053',
            'country' => 'India',
            'is_active' => true,
        ]);
        $bandra = SaloonBranch::query()->create([
            'saloon_id' => $owner->saloon_id,
            'branch_name' => 'Bandra',
            'business_address_1' => 'Bandra Road',
            'city' => 'Mumbai',
            'state' => 'Maharashtra',
            'area_pincode' => '400050',
            'country' => 'India',
            'is_active' => true,
        ]);

        $managerRole = Role::findByCode(RoleCodes::SALON_BRANCH_MANAGER);
        $staffRole = Role::findByCode(RoleCodes::SALON_STAFF);
        $manager = User::query()->create([
            'name' => 'Staff Scope Manager',
            'firstname' => 'Staff',
            'lastname' => 'Manager',
            'email' => 'manager-staff-scope@test.com',
            'phone' => '+917100000312',
            'password' => 'Pass@1234',
            'role_id' => $managerRole->id,
            'saloon_id' => $owner->saloon_id,
            'branch_id' => $andheri->id,
            'is_active' => true,
            'onboarding_completed_at' => now(),
        ]);

        $this->actingAs($manager)
            ->postJson('/api/v1/staff', [
                'firstname' => 'New',
                'lastname' => 'Stylist',
                'email' => 'stylist-scope@test.com',
                'phone' => '+917100000313',
                'password' => 'Pass@1234',
                'password_confirmation' => 'Pass@1234',
                'role_id' => $staffRole->id,
                'branch_id' => $bandra->id,
                'is_active' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.staff.branch_id', $andheri->id);

        $this->assertDatabaseHas('users', [
            'email' => 'stylist-scope@test.com',
            'branch_id' => $andheri->id,
        ]);
    }

    public function test_appointment_staff_must_belong_to_selected_branch(): void
    {
        $owner = $this->createOwnerUser([
            'email' => 'owner-appt-scope@test.com',
            'phone' => '+917100000321',
        ]);
        $this->assignDefaultSubscription($owner->saloon, 'pro');

        $andheri = SaloonBranch::query()->create([
            'saloon_id' => $owner->saloon_id,
            'branch_name' => 'Andheri',
            'business_address_1' => 'Andheri Road',
            'city' => 'Mumbai',
            'state' => 'Maharashtra',
            'area_pincode' => '400053',
            'country' => 'India',
            'is_active' => true,
        ]);
        $bandra = SaloonBranch::query()->create([
            'saloon_id' => $owner->saloon_id,
            'branch_name' => 'Bandra',
            'business_address_1' => 'Bandra Road',
            'city' => 'Mumbai',
            'state' => 'Maharashtra',
            'area_pincode' => '400050',
            'country' => 'India',
            'is_active' => true,
        ]);

        $staffRole = Role::findByCode(RoleCodes::SALON_STAFF);
        $bandraStaff = User::query()->create([
            'name' => 'Bandra Stylist',
            'firstname' => 'Bandra',
            'lastname' => 'Stylist',
            'email' => 'bandra-stylist@test.com',
            'phone' => '+917100000322',
            'password' => 'Pass@1234',
            'role_id' => $staffRole->id,
            'saloon_id' => $owner->saloon_id,
            'branch_id' => $bandra->id,
            'is_active' => true,
            'onboarding_completed_at' => now(),
        ]);

        $category = Category::query()->create(['name' => 'Hair Scope Appt', 'is_active' => true]);
        $service = Service::query()->create([
            'name' => 'Cross Branch Cut',
            'category_id' => $category->id,
            'default_price' => 500,
            'duration_minutes' => 30,
            'is_active' => true,
        ]);
        SalonServiceProduct::query()->create([
            'saloon_id' => $owner->saloon_id,
            'branch_id' => null,
            'service_id' => $service->id,
            'product_id' => null,
            'price' => 500,
            'duration_minutes' => 30,
            'is_active' => true,
        ]);

        $this->actingAs($owner)
            ->postJson('/api/v1/appointments', [
                'branch_id' => $andheri->id,
                'type' => 'appointment',
                'customer_name' => 'Cross Branch Guest',
                'customer_phone' => '+919700000321',
                'starts_at' => now()->addDay()->setTime(10, 0)->toIso8601String(),
                'status' => 'scheduled',
                'services' => [[
                    'service_id' => $service->id,
                    'staff_id' => $bandraStaff->id,
                    'price' => 500,
                    'duration_minutes' => 30,
                ]],
            ])
            ->assertForbidden()
            ->assertJsonPath('message', 'Selected staff member does not belong to this branch.');
    }

    public function test_branch_manager_catalog_is_forced_to_own_branch(): void
    {
        $owner = $this->createOwnerUser([
            'email' => 'owner-catalog-scope@test.com',
            'phone' => '+917100000331',
        ]);
        $this->assignDefaultSubscription($owner->saloon, 'pro');

        $andheri = SaloonBranch::query()->create([
            'saloon_id' => $owner->saloon_id,
            'branch_name' => 'Andheri',
            'business_address_1' => 'Andheri Road',
            'city' => 'Mumbai',
            'state' => 'Maharashtra',
            'area_pincode' => '400053',
            'country' => 'India',
            'is_active' => true,
        ]);
        $bandra = SaloonBranch::query()->create([
            'saloon_id' => $owner->saloon_id,
            'branch_name' => 'Bandra',
            'business_address_1' => 'Bandra Road',
            'city' => 'Mumbai',
            'state' => 'Maharashtra',
            'area_pincode' => '400050',
            'country' => 'India',
            'is_active' => true,
        ]);

        $category = Category::query()->create([
            'name' => 'Hair Scope',
            'is_active' => true,
        ]);
        $service = Service::query()->create([
            'name' => 'Haircut Scope',
            'category_id' => $category->id,
            'default_price' => 500,
            'duration_minutes' => 30,
            'is_active' => true,
        ]);
        $product = Product::query()->create([
            'name' => 'Shampoo Scope',
            'category_id' => $category->id,
            'is_active' => true,
        ]);

        $bandraItem = SalonServiceProduct::query()->create([
            'saloon_id' => $owner->saloon_id,
            'branch_id' => $bandra->id,
            'service_id' => $service->id,
            'product_id' => $product->id,
            'price' => 500,
            'duration_minutes' => 30,
            'is_active' => true,
        ]);

        $managerRole = Role::findByCode(RoleCodes::SALON_BRANCH_MANAGER);
        $manager = User::query()->create([
            'name' => 'Catalog Manager',
            'firstname' => 'Catalog',
            'lastname' => 'Manager',
            'email' => 'manager-catalog-scope@test.com',
            'phone' => '+917100000332',
            'password' => 'Pass@1234',
            'role_id' => $managerRole->id,
            'saloon_id' => $owner->saloon_id,
            'branch_id' => $andheri->id,
            'is_active' => true,
            'onboarding_completed_at' => now(),
        ]);

        $this->actingAs($manager);

        $this->postJson('/api/v1/catalog', [
            'service_id' => $service->id,
            'product_id' => $product->id,
            'price' => 750,
            'duration_minutes' => 45,
            'is_active' => true,
            'branch_id' => $bandra->id,
        ])
            ->assertCreated()
            ->assertJsonPath('data.catalog.branch_id', $andheri->id);

        $catalogResponse = $this->getJson('/api/v1/catalog?page=1&per_page=50')->assertOk();
        $catalogItemIds = array_column($catalogResponse->json('data.catalog'), 'id');
        $this->assertNotContains($bandraItem->id, $catalogItemIds);

        $this->patchJson("/api/v1/catalog/{$bandraItem->id}", [
            'service_id' => $service->id,
            'product_id' => $product->id,
            'price' => 999,
            'duration_minutes' => 60,
            'is_active' => true,
        ])
            ->assertForbidden()
            ->assertJsonPath('message', 'You can only manage catalog items for your current branch.');
    }

    public function test_branch_manager_can_view_branch_roles_but_not_assign_owner(): void
    {
        $owner = $this->createOwnerUser([
            'email' => 'owner-role-scope@test.com',
            'phone' => '+917100000341',
        ]);
        $this->assignDefaultSubscription($owner->saloon, 'pro');

        $andheri = SaloonBranch::query()->create([
            'saloon_id' => $owner->saloon_id,
            'branch_name' => 'Andheri',
            'business_address_1' => 'Andheri Road',
            'city' => 'Mumbai',
            'state' => 'Maharashtra',
            'area_pincode' => '400053',
            'country' => 'India',
            'is_active' => true,
        ]);

        $managerRole = Role::findByCode(RoleCodes::SALON_BRANCH_MANAGER);
        $staffRole = Role::findByCode(RoleCodes::SALON_STAFF);
        $ownerRole = Role::findByCode(RoleCodes::SALON_FRANCHISE_OWNER);

        $manager = User::query()->create([
            'name' => 'Role Manager',
            'firstname' => 'Role',
            'lastname' => 'Manager',
            'email' => 'manager-role-scope@test.com',
            'phone' => '+917100000342',
            'password' => 'Pass@1234',
            'role_id' => $managerRole->id,
            'saloon_id' => $owner->saloon_id,
            'branch_id' => $andheri->id,
            'is_active' => true,
            'onboarding_completed_at' => now(),
        ]);

        $this->actingAs($manager);

        $this->getJson('/api/v1/roles?page=1&per_page=50')
            ->assertOk()
            ->assertJsonFragment(['code' => RoleCodes::SALON_STAFF])
            ->assertJsonFragment(['code' => RoleCodes::SALON_BRANCH_MANAGER])
            ->assertJsonMissing(['code' => RoleCodes::SALON_FRANCHISE_OWNER]);

        $this->postJson('/api/v1/staff', [
            'firstname' => 'Escalated',
            'lastname' => 'User',
            'email' => 'escalated-user@test.com',
            'phone' => '+917100000343',
            'password' => 'Pass@1234',
            'password_confirmation' => 'Pass@1234',
            'role_id' => $ownerRole->id,
            'branch_id' => $andheri->id,
            'is_active' => true,
        ])
            ->assertForbidden()
            ->assertJsonPath('message', 'You can only access roles for your current branch.');

        $this->postJson('/api/v1/staff', [
            'firstname' => 'Ok',
            'lastname' => 'Stylist',
            'email' => 'ok-stylist@test.com',
            'phone' => '+917100000344',
            'password' => 'Pass@1234',
            'password_confirmation' => 'Pass@1234',
            'role_id' => $staffRole->id,
            'branch_id' => $andheri->id,
            'is_active' => true,
        ])
            ->assertCreated()
            ->assertJsonPath('data.staff.role.code', RoleCodes::SALON_STAFF);
    }

    public function test_branch_manager_can_create_branch_role_and_assign_only_own_permissions(): void
    {
        $owner = $this->createOwnerUser([
            'email' => 'owner-role-handdown@test.com',
            'phone' => '+917100000351',
        ]);
        $this->assignDefaultSubscription($owner->saloon, 'pro');

        $andheri = SaloonBranch::query()->create([
            'saloon_id' => $owner->saloon_id,
            'branch_name' => 'Andheri',
            'business_address_1' => 'Andheri Road',
            'city' => 'Mumbai',
            'state' => 'Maharashtra',
            'area_pincode' => '400053',
            'country' => 'India',
            'is_active' => true,
        ]);
        $bandra = SaloonBranch::query()->create([
            'saloon_id' => $owner->saloon_id,
            'branch_name' => 'Bandra',
            'business_address_1' => 'Bandra Road',
            'city' => 'Mumbai',
            'state' => 'Maharashtra',
            'area_pincode' => '400050',
            'country' => 'India',
            'is_active' => true,
        ]);

        $managerRole = Role::findByCode(RoleCodes::SALON_BRANCH_MANAGER);
        $manager = User::query()->create([
            'name' => 'Handdown Manager',
            'firstname' => 'Hand',
            'lastname' => 'Down',
            'email' => 'manager-handdown@test.com',
            'phone' => '+917100000352',
            'password' => 'Pass@1234',
            'role_id' => $managerRole->id,
            'saloon_id' => $owner->saloon_id,
            'branch_id' => $andheri->id,
            'is_active' => true,
            'onboarding_completed_at' => now(),
        ]);

        $this->actingAs($manager);

        $create = $this->postJson('/api/v1/roles', [
            'name' => 'Andheri Stylist Lead',
            'is_active' => true,
            'scope' => 'salon',
            'branch_id' => $bandra->id,
            'hierarchy_level' => 10,
        ])->assertCreated();

        $create
            ->assertJsonPath('data.role.scope', 'branch')
            ->assertJsonPath('data.role.branch_id', $andheri->id)
            ->assertJsonPath('data.role.hierarchy_level', 31);

        $roleId = (int) $create->json('data.role.id');

        $permissionResponse = $this->getJson('/api/v1/permissions')->assertOk();
        $permissionCodes = collect($permissionResponse->json('data.permissions'))->pluck('code');
        $this->assertTrue($permissionCodes->contains('staff.view'));
        $this->assertTrue($permissionCodes->contains('assign_permissions.update'));
        $this->assertFalse($permissionCodes->contains('branches.create'));
        $this->assertSame(
            $permissionCodes->count(),
            count(\Database\Seeders\ApplicationPermissionSeeder::branchManagerPermissionCodes()),
        );

        $staffViewId = (string) Permission::query()->where('code', 'staff.view')->value('id');
        $branchesCreateId = (string) Permission::query()->where('code', 'branches.create')->value('id');

        $this->putJson("/api/v1/roles/{$roleId}/permissions", [
            'permission_ids' => [$staffViewId, $branchesCreateId],
        ])
            ->assertOk()
            ->assertJsonCount(1, 'data.role.permissions')
            ->assertJsonPath('data.role.permissions.0.code', 'staff.view');

        $this->assertDatabaseHas('permission_role', [
            'role_id' => $roleId,
            'permission_id' => $staffViewId,
        ]);
        $this->assertDatabaseMissing('permission_role', [
            'role_id' => $roleId,
            'permission_id' => $branchesCreateId,
        ]);
    }
}
