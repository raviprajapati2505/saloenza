<?php

namespace Tests\Feature\Branch;

use App\Models\Category;
use App\Models\Product;
use App\Models\Role;
use App\Models\SalonServiceProduct;
use App\Models\SaloonBranch;
use App\Models\Service;
use App\Support\Role\RoleCodes;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BranchApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedReferenceData();
        $this->seed(SubscriptionPlanSeeder::class);
    }

    public function test_owner_can_create_branch_with_manager_and_copied_catalog(): void
    {
        $owner = $this->createOwnerUser([
            'email' => 'owner-branch@test.com',
            'phone' => '+917100000001',
        ]);
        $this->assignDefaultSubscription($owner->saloon, 'pro');

        $mainBranch = SaloonBranch::query()->create([
            'saloon_id' => $owner->saloon_id,
            'branch_name' => 'Main Branch',
            'business_address_1' => 'Main Street',
            'city' => 'Mumbai',
            'state' => 'Maharashtra',
            'area_pincode' => '400001',
            'country' => 'India',
            'is_active' => true,
        ]);
        $owner->update(['branch_id' => $mainBranch->id]);

        $category = Category::query()->create([
            'name' => 'Hair',
            'is_active' => true,
        ]);
        $service = Service::query()->create([
            'name' => 'Haircut',
            'category_id' => $category->id,
            'default_price' => 500,
            'duration_minutes' => 45,
            'is_active' => true,
        ]);
        $product = Product::query()->create([
            'name' => 'Shampoo',
            'category_id' => $category->id,
            'is_active' => true,
        ]);
        SalonServiceProduct::query()->create([
            'saloon_id' => $owner->saloon_id,
            'branch_id' => $mainBranch->id,
            'service_id' => $service->id,
            'product_id' => $product->id,
            'price' => 650,
            'duration_minutes' => 45,
            'is_active' => true,
        ]);

        $customRole = Role::query()->create([
            'name' => 'Reception Lead',
            'code' => 'salon.reception.lead',
            'is_active' => true,
            'is_system' => false,
            'scope' => 'branch',
            'hierarchy_level' => 35,
            'saloon_id' => $owner->saloon_id,
            'branch_id' => $mainBranch->id,
        ]);
        $customRole->permissions()->sync(
            Role::findByCode(RoleCodes::SALON_BRANCH_MANAGER)?->permissions()->pluck('permissions.id')->all() ?? [],
        );

        $this->actingAs($owner);

        $response = $this->postJson('/api/v1/branches', [
            'branch_name' => 'Bandra Branch',
            'business_address_1' => 'Hill Road',
            'business_address_2' => 'Above Cafe',
            'city' => 'Mumbai',
            'state' => 'Maharashtra',
            'area_pincode' => '400050',
            'country' => 'India',
            'is_active' => true,
            'template_branch_id' => $mainBranch->id,
            'manager' => [
                'firstname' => 'Anita',
                'lastname' => 'Manager',
                'email' => 'bandra.manager@test.com',
                'phone' => '+917100000002',
                'password' => 'Pass@1234',
                'password_confirmation' => 'Pass@1234',
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('message', 'Branch created successfully.')
            ->assertJsonPath('data.branch.branch_name', 'Bandra Branch')
            ->assertJsonPath('data.manager.email', 'bandra.manager@test.com')
            ->assertJsonPath('data.cloned_catalog_items', 1)
            ->assertJsonPath('data.cloned_roles', 1);

        $newBranchId = (int) $response->json('data.branch.id');

        $this->assertDatabaseHas('saloon_branches', [
            'id' => $newBranchId,
            'branch_name' => 'Bandra Branch',
        ]);
        $this->assertDatabaseHas('users', [
            'email' => 'bandra.manager@test.com',
            'branch_id' => $newBranchId,
        ]);
        $this->assertDatabaseHas('salon_service_products', [
            'saloon_id' => $owner->saloon_id,
            'branch_id' => $newBranchId,
            'service_id' => $service->id,
            'product_id' => $product->id,
            'price' => '650.00',
        ]);
        $this->assertDatabaseHas('roles', [
            'saloon_id' => $owner->saloon_id,
            'branch_id' => $newBranchId,
            'name' => 'Reception Lead (Bandra Branch)',
        ]);
    }

    public function test_owner_can_update_existing_branch(): void
    {
        $owner = $this->actingAsOwner([
            'email' => 'branch-update@test.com',
            'phone' => '+917100000011',
        ]);

        $branch = SaloonBranch::query()->create([
            'saloon_id' => $owner->saloon_id,
            'branch_name' => 'Old Branch',
            'business_address_1' => 'Old Road',
            'city' => 'Pune',
            'state' => 'Maharashtra',
            'area_pincode' => '411001',
            'country' => 'India',
            'is_active' => true,
        ]);

        $this->patchJson("/api/v1/branches/{$branch->id}", [
            'branch_name' => 'Updated Branch',
            'business_address_1' => 'New Road',
            'business_address_2' => 'Near Mall',
            'city' => 'Mumbai',
            'state' => 'Maharashtra',
            'area_pincode' => '400099',
            'country' => 'India',
            'is_active' => false,
        ])->assertOk()
            ->assertJsonPath('message', 'Branch updated successfully.')
            ->assertJsonPath('data.branch.branch_name', 'Updated Branch')
            ->assertJsonPath('data.branch.is_active', false);

        $this->assertDatabaseHas('saloon_branches', [
            'id' => $branch->id,
            'branch_name' => 'Updated Branch',
            'city' => 'Mumbai',
            'is_active' => false,
        ]);
    }
}
