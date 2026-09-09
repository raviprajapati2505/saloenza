<?php

namespace Tests\Feature\Branch;

use App\Models\Saloon;
use App\Models\SaloonBranch;
use App\Models\SubscriptionPlan;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminBranchApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedReferenceData();
        $this->seed(SubscriptionPlanSeeder::class);
    }

    public function test_platform_admin_can_list_all_salon_branches(): void
    {
        $admin = $this->createSystemAdmin();

        $salonA = Saloon::query()->create([
            'name' => 'Salon Alpha',
            'city' => 'Mumbai',
            'state' => 'Maharashtra',
            'is_active' => true,
        ]);
        $salonB = Saloon::query()->create([
            'name' => 'Salon Beta',
            'city' => 'Pune',
            'state' => 'Maharashtra',
            'is_active' => true,
        ]);

        SaloonBranch::query()->create([
            'saloon_id' => $salonA->id,
            'branch_name' => 'Alpha Main',
            'business_address_1' => 'Alpha Street',
            'city' => 'Mumbai',
            'state' => 'Maharashtra',
            'area_pincode' => '400001',
            'country' => 'India',
            'is_active' => true,
        ]);
        SaloonBranch::query()->create([
            'saloon_id' => $salonB->id,
            'branch_name' => 'Beta Main',
            'business_address_1' => 'Beta Street',
            'city' => 'Pune',
            'state' => 'Maharashtra',
            'area_pincode' => '411001',
            'country' => 'India',
            'is_active' => true,
        ]);

        $this->actingAs($admin);

        $this->getJson('/api/v1/admin/branches?page=1&per_page=50')
            ->assertOk()
            ->assertJsonPath('message', 'Platform branches fetched successfully.')
            ->assertJsonFragment(['branch_name' => 'Alpha Main'])
            ->assertJsonFragment(['branch_name' => 'Beta Main'])
            ->assertJsonFragment(['name' => 'Salon Alpha']);

        $this->getJson('/api/v1/admin/branches?page=1&per_page=1&search=Alpha')
            ->assertOk()
            ->assertJsonPath('data.meta.per_page', 1)
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.meta.last_page', 1)
            ->assertJsonCount(1, 'data.branches')
            ->assertJsonFragment(['branch_name' => 'Alpha Main']);

        $this->getJson("/api/v1/admin/branches?page=1&per_page=50&saloon_id={$salonA->id}")
            ->assertOk()
            ->assertJsonFragment(['branch_name' => 'Alpha Main'])
            ->assertJsonMissing(['branch_name' => 'Beta Main']);
    }

    public function test_platform_admin_is_blocked_when_salon_branch_limit_is_reached(): void
    {
        $admin = $this->createSystemAdmin();
        $owner = $this->createOwnerUser([
            'email' => 'owner-limit@test.com',
            'phone' => '+917100000101',
        ]);
        $this->assignDefaultSubscription($owner->saloon, 'basic');

        SaloonBranch::query()->create([
            'saloon_id' => $owner->saloon_id,
            'branch_name' => 'Only Allowed Branch',
            'business_address_1' => 'Main Road',
            'city' => 'Mumbai',
            'state' => 'Maharashtra',
            'area_pincode' => '400001',
            'country' => 'India',
            'is_active' => true,
        ]);

        $basic = SubscriptionPlan::query()->where('slug', 'basic')->first();
        $this->assertNotNull($basic);
        $this->assertSame(1, (int) $basic->max_branches);

        $this->actingAs($owner);
        $this->postJson('/api/v1/branches', [
            'branch_name' => 'Owner Second Branch',
            'business_address_1' => 'Second Road',
            'city' => 'Mumbai',
            'state' => 'Maharashtra',
            'area_pincode' => '400002',
            'country' => 'India',
            'is_active' => true,
        ])->assertForbidden();

        $this->actingAs($admin);
        $this->postJson('/api/v1/admin/branches', [
            'saloon_id' => $owner->saloon_id,
            'branch_name' => 'Platform Created Branch',
            'business_address_1' => 'Admin Road',
            'city' => 'Mumbai',
            'state' => 'Maharashtra',
            'area_pincode' => '400003',
            'country' => 'India',
            'is_active' => true,
        ])->assertForbidden()
            ->assertJsonPath('message', 'Branch limit reached (1). Upgrade your subscription to add more branches.');

        $this->assertDatabaseMissing('saloon_branches', [
            'saloon_id' => $owner->saloon_id,
            'branch_name' => 'Platform Created Branch',
        ]);
    }

    public function test_platform_admin_can_fetch_salon_branch_capacity(): void
    {
        $admin = $this->createSystemAdmin();
        $owner = $this->createOwnerUser([
            'email' => 'capacity@test.com',
            'phone' => '+917100000201',
        ]);
        $this->assignDefaultSubscription($owner->saloon, 'basic');

        SaloonBranch::query()->create([
            'saloon_id' => $owner->saloon_id,
            'branch_name' => 'Capacity Branch',
            'business_address_1' => 'Main Road',
            'city' => 'Mumbai',
            'state' => 'Maharashtra',
            'area_pincode' => '400001',
            'country' => 'India',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->getJson("/api/v1/admin/saloons/{$owner->saloon_id}/branch-capacity")
            ->assertOk()
            ->assertJsonPath('message', 'Salon branch capacity fetched successfully.')
            ->assertJsonPath('data.limits.branches_used', 1)
            ->assertJsonPath('data.limits.max_branches', 1)
            ->assertJsonPath('data.can_create_branch', false);
    }

    public function test_platform_admin_can_update_any_branch(): void
    {
        $admin = $this->createSystemAdmin();
        $salon = Saloon::query()->create([
            'name' => 'Editable Salon',
            'city' => 'Delhi',
            'state' => 'Delhi',
            'is_active' => true,
        ]);
        $branch = SaloonBranch::query()->create([
            'saloon_id' => $salon->id,
            'branch_name' => 'Old Name',
            'business_address_1' => 'Old Address',
            'city' => 'Delhi',
            'state' => 'Delhi',
            'area_pincode' => '110001',
            'country' => 'India',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->patchJson("/api/v1/admin/branches/{$branch->id}", [
                'branch_name' => 'New Name',
                'business_address_1' => 'New Address',
                'business_address_2' => null,
                'city' => 'Noida',
                'state' => 'Uttar Pradesh',
                'area_pincode' => '201301',
                'country' => 'India',
                'is_active' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.branch.branch_name', 'New Name')
            ->assertJsonPath('data.branch.is_active', false);

        $this->assertDatabaseHas('saloon_branches', [
            'id' => $branch->id,
            'branch_name' => 'New Name',
            'city' => 'Noida',
            'is_active' => false,
        ]);
    }
}
