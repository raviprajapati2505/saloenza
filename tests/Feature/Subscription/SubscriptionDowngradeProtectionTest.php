<?php

namespace Tests\Feature\Subscription;

use App\Models\SaloonBranch;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Support\Role\RoleCodes;
use App\Support\Subscription\SubscriptionEntitlements;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionDowngradeProtectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedReferenceData();
        $this->seed(SubscriptionPlanSeeder::class);
    }

    public function test_subscription_api_marks_ineligible_downgrade_plans(): void
    {
        config([
            'payment.driver' => 'manual',
            'payment.allow_instant_upgrade' => false,
        ]);

        $owner = $this->actingAsOwner(['email' => 'downgrade.owner@gmail.com']);
        $saloon = $owner->saloon;
        $proPlan = SubscriptionPlan::query()->where('slug', 'pro')->firstOrFail();
        $basicPlan = SubscriptionPlan::query()->where('slug', 'basic')->firstOrFail();

        app(SubscriptionEntitlements::class)->assignPlan($saloon, $proPlan, startTrial: false);

        SaloonBranch::query()->create([
            'saloon_id' => $saloon->id,
            'branch_name' => 'Branch A',
            'business_address_1' => '123 Test Street',
            'city' => 'Mumbai',
            'state' => 'Maharashtra',
            'area_pincode' => '400001',
            'country' => 'India',
            'is_active' => true,
        ]);
        SaloonBranch::query()->create([
            'saloon_id' => $saloon->id,
            'branch_name' => 'Branch B',
            'business_address_1' => '456 Test Street',
            'city' => 'Mumbai',
            'state' => 'Maharashtra',
            'area_pincode' => '400002',
            'country' => 'India',
            'is_active' => true,
        ]);

        $branch = SaloonBranch::query()->where('saloon_id', $saloon->id)->firstOrFail();
        $staffRole = \App\Models\Role::findByCode(RoleCodes::SALON_STAFF);

        for ($index = 1; $index <= 6; $index++) {
            User::factory()->create([
                'saloon_id' => $saloon->id,
                'branch_id' => $branch->id,
                'role_id' => $staffRole->id,
                'is_active' => true,
                'email' => "staff{$index}.downgrade@gmail.com",
                'phone' => '+9170000001'.str_pad((string) $index, 2, '0', STR_PAD_LEFT),
            ]);
        }

        $response = $this->getJson('/api/v1/subscription')
            ->assertOk()
            ->assertJsonPath('data.plan.slug', 'pro');

        $planOptions = collect($response->json('data.plan_options'));
        $basicOption = $planOptions->first(fn (array $row) => ($row['plan']['slug'] ?? null) === 'basic');

        $this->assertNotNull($basicOption);
        $this->assertFalse($basicOption['eligibility']['allowed']);
        $this->assertTrue($basicOption['eligibility']['is_downgrade']);
        $this->assertStringContainsString('Cannot downgrade to Basic', $basicOption['eligibility']['message']);
        $this->assertCount(2, $basicOption['eligibility']['blockers']);
    }

    public function test_tenant_cannot_checkout_downgrade_when_over_limits(): void
    {
        config([
            'payment.driver' => 'manual',
            'payment.allow_instant_upgrade' => false,
        ]);

        $owner = $this->actingAsOwner(['email' => 'checkout.downgrade@gmail.com']);
        $saloon = $owner->saloon;
        $proPlan = SubscriptionPlan::query()->where('slug', 'pro')->firstOrFail();
        $basicPlan = SubscriptionPlan::query()->where('slug', 'basic')->firstOrFail();

        app(SubscriptionEntitlements::class)->assignPlan($saloon, $proPlan, startTrial: false);

        SaloonBranch::query()->create([
            'saloon_id' => $saloon->id,
            'branch_name' => 'Branch A',
            'business_address_1' => '123 Test Street',
            'city' => 'Mumbai',
            'state' => 'Maharashtra',
            'area_pincode' => '400001',
            'country' => 'India',
            'is_active' => true,
        ]);
        SaloonBranch::query()->create([
            'saloon_id' => $saloon->id,
            'branch_name' => 'Branch B',
            'business_address_1' => '456 Test Street',
            'city' => 'Mumbai',
            'state' => 'Maharashtra',
            'area_pincode' => '400002',
            'country' => 'India',
            'is_active' => true,
        ]);

        $this->postJson('/api/v1/subscription/checkout', [
            'subscription_plan_id' => $basicPlan->id,
            'notes' => 'Trying to downgrade.',
        ])
            ->assertStatus(422)
            ->assertJsonFragment([
                'message' => 'Cannot downgrade to Basic. You have 2 active branches but Basic allows only 1. Remove extra branches or staff before downgrading.',
            ]);
    }

    public function test_platform_admin_cannot_assign_downgrade_when_over_limits(): void
    {
        config(['payment.driver' => 'manual']);

        $owner = $this->createOwnerUser(['email' => 'admin.downgrade@gmail.com']);
        $saloon = $owner->saloon;
        $proPlan = SubscriptionPlan::query()->where('slug', 'pro')->firstOrFail();
        $basicPlan = SubscriptionPlan::query()->where('slug', 'basic')->firstOrFail();

        app(SubscriptionEntitlements::class)->assignPlan($saloon, $proPlan, startTrial: false);

        SaloonBranch::query()->create([
            'saloon_id' => $saloon->id,
            'branch_name' => 'Branch A',
            'business_address_1' => '123 Test Street',
            'city' => 'Mumbai',
            'state' => 'Maharashtra',
            'area_pincode' => '400001',
            'country' => 'India',
            'is_active' => true,
        ]);
        SaloonBranch::query()->create([
            'saloon_id' => $saloon->id,
            'branch_name' => 'Branch B',
            'business_address_1' => '456 Test Street',
            'city' => 'Mumbai',
            'state' => 'Maharashtra',
            'area_pincode' => '400002',
            'country' => 'India',
            'is_active' => true,
        ]);

        $admin = $this->actingAsSystemAdmin(['email' => 'admin.block.downgrade@gmail.com']);

        $this->postJson("/api/v1/admin/saloons/{$saloon->id}/subscription/assign", [
            'subscription_plan_id' => $basicPlan->id,
            'payment_type' => 'cash',
            'amount' => 999,
            'transaction_id' => 'CASH123',
            'notes' => 'Attempt downgrade',
        ])
            ->assertStatus(422)
            ->assertJsonFragment([
                'message' => 'Cannot downgrade to Basic. You have 2 active branches but Basic allows only 1. Remove extra branches or staff before downgrading.',
            ]);
    }
}
