<?php

namespace Tests\Feature\Subscription;

use App\Models\SaloonBranch;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Support\Role\RoleCodes;
use App\Support\Subscription\SubscriptionEntitlements;
use App\Support\Subscription\SubscriptionModules;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionPlanApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedReferenceData();
        $this->seed(SubscriptionPlanSeeder::class);
    }

    public function test_system_admin_can_manage_subscription_plans(): void
    {
        $this->actingAsSystemAdmin();

        $createResponse = $this->postJson('/api/v1/subscription-plans', [
            'name' => 'Starter',
            'slug' => 'starter',
            'description' => 'Starter plan',
            'price' => 499,
            'billing_interval' => 'monthly',
            'trial_days' => 0,
            'max_branches' => 1,
            'max_staff' => 4,
            'modules' => [
                SubscriptionModules::APPOINTMENTS,
                SubscriptionModules::CUSTOMERS,
                SubscriptionModules::STAFF,
            ],
            'is_active' => true,
            'is_public' => true,
            'sort_order' => 5,
        ]);

        $createResponse
            ->assertCreated()
            ->assertJsonPath('data.subscription_plan.slug', 'starter')
            ->assertJsonPath('data.subscription_plan.max_branches', 1);

        $planId = (int) $createResponse->json('data.subscription_plan.id');

        $this->getJson('/api/v1/subscription-plans?page=1&per_page=10')
            ->assertOk()
            ->assertJsonPath('message', 'Subscription plans fetched successfully.');

        $this->putJson("/api/v1/subscription-plans/{$planId}", [
            'name' => 'Starter Plus',
            'slug' => 'starter-plus',
            'description' => 'Updated starter plan',
            'price' => 699,
            'billing_interval' => 'monthly',
            'trial_days' => 0,
            'max_branches' => 2,
            'max_staff' => 6,
            'modules' => [
                SubscriptionModules::APPOINTMENTS,
                SubscriptionModules::CUSTOMERS,
                SubscriptionModules::STAFF,
                SubscriptionModules::BILLING,
            ],
            'is_active' => true,
            'is_public' => true,
            'sort_order' => 6,
        ])->assertOk()
            ->assertJsonPath('data.subscription_plan.slug', 'starter-plus')
            ->assertJsonPath('data.subscription_plan.max_staff', 6);

        $this->deleteJson("/api/v1/subscription-plans/{$planId}")
            ->assertOk()
            ->assertJsonPath('message', 'Subscription plan deleted successfully.');
    }

    public function test_authenticated_owner_can_list_public_subscription_plans(): void
    {
        $owner = $this->actingAsOwner(['email' => 'plans.owner@gmail.com']);

        $response = $this->getJson('/api/v1/subscription-plans?page=1&per_page=10');

        $response->assertOk()
            ->assertJsonPath('message', 'Subscription plans fetched successfully.');

        $this->assertNotEmpty($response->json('data.subscription_plans.data') ?? $response->json('data.subscription_plans'));
    }

    public function test_staff_limit_is_enforced_for_basic_plan(): void
    {
        $owner = $this->createOwnerUser(['email' => 'limits.owner@gmail.com']);
        $basicPlan = SubscriptionPlan::query()->where('slug', 'basic')->firstOrFail();

        app(SubscriptionEntitlements::class)->assignPlan($owner->saloon, $basicPlan);

        SaloonBranch::query()->create([
            'saloon_id' => $owner->saloon_id,
            'branch_name' => 'Main Branch',
            'business_address_1' => '123 Main Street',
            'city' => 'Mumbai',
            'state' => 'Maharashtra',
            'area_pincode' => '400001',
            'country' => 'India',
            'is_active' => true,
        ]);

        $staffRole = \App\Models\Role::findByCode(RoleCodes::SALON_STAFF);

        for ($index = 0; $index < 5; $index++) {
            User::factory()->create([
                'saloon_id' => $owner->saloon_id,
                'role_id' => $staffRole->id,
                'phone' => '+9170000001'.str_pad((string) $index, 2, '0', STR_PAD_LEFT),
                'is_active' => true,
            ]);
        }

        $this->actingAs($owner);

        $this->postJson('/api/v1/staff', [
            'firstname' => 'Extra',
            'lastname' => 'Staff',
            'email' => 'extra.staff@gmail.com',
            'phone' => '+917000000199',
            'password' => 'Password@123',
            'password_confirmation' => 'Password@123',
            'role_id' => $staffRole->id,
            'is_active' => true,
        ])->assertForbidden()
            ->assertJsonPath('message', 'Staff limit reached (5). Upgrade your subscription to add more staff.');
    }

    public function test_branch_limit_is_enforced_for_basic_plan(): void
    {
        $owner = $this->createOwnerUser(['email' => 'branch.owner@gmail.com']);
        $basicPlan = SubscriptionPlan::query()->where('slug', 'basic')->firstOrFail();

        app(SubscriptionEntitlements::class)->assignPlan($owner->saloon, $basicPlan);

        SaloonBranch::query()->create([
            'saloon_id' => $owner->saloon_id,
            'branch_name' => 'Main Branch',
            'business_address_1' => '123 Main Street',
            'city' => 'Mumbai',
            'state' => 'Maharashtra',
            'area_pincode' => '400001',
            'country' => 'India',
            'is_active' => true,
        ]);

        $this->actingAs($owner);

        $this->postJson('/api/v1/branches', [
            'branch_name' => 'Second Branch',
            'business_address_1' => '456 Side Street',
            'city' => 'Mumbai',
            'state' => 'Maharashtra',
            'area_pincode' => '400002',
            'country' => 'India',
            'is_active' => true,
        ])->assertForbidden()
            ->assertJsonPath('message', 'Branch limit reached (1). Upgrade your subscription to add more branches.');
    }

    public function test_subscription_module_middleware_blocks_routes_without_module(): void
    {
        $owner = $this->createOwnerUser(['email' => 'module.owner@gmail.com']);

        $limitedPlan = SubscriptionPlan::query()->create([
            'name' => 'Catalog Only',
            'slug' => 'catalog-only-test',
            'description' => 'Test plan without appointments',
            'price' => 100,
            'billing_interval' => 'monthly',
            'trial_days' => 0,
            'max_branches' => 1,
            'max_staff' => 2,
            'modules' => [SubscriptionModules::CATALOG, SubscriptionModules::SETTINGS],
            'is_active' => true,
            'is_public' => false,
            'sort_order' => 99,
        ]);

        app(SubscriptionEntitlements::class)->assignPlan($owner->saloon, $limitedPlan);

        $this->actingAs($owner);

        $this->getJson('/api/v1/appointments?page=1&per_page=10')
            ->assertForbidden()
            ->assertJsonPath(
                'message',
                'The Appointments module is not included in Catalog Only. Please upgrade your subscription.',
            )
            ->assertJsonPath('required_modules.0', SubscriptionModules::APPOINTMENTS);
    }

    public function test_owner_can_upgrade_subscription_plan(): void
    {
        $owner = $this->createOwnerUser(['email' => 'upgrade.owner@gmail.com']);
        $proPlan = SubscriptionPlan::query()->where('slug', 'pro')->firstOrFail();

        $this->actingAs($owner);

        $this->postJson('/api/v1/subscription/upgrade', [
            'subscription_plan_id' => $proPlan->id,
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Subscription plan updated successfully.')
            ->assertJsonPath('data.plan.slug', 'pro');

        $this->getJson('/api/v1/subscription')
            ->assertOk()
            ->assertJsonPath('data.plan.slug', 'pro');
    }
}
