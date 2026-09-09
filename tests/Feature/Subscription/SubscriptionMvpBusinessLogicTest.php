<?php

namespace Tests\Feature\Subscription;

use App\Models\SubscriptionPlan;
use App\Models\SubscriptionUpgradeOrder;
use App\Support\Subscription\SubscriptionEntitlements;
use App\Support\Subscription\SubscriptionModules;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * MVP subscription / billing / module-gating business rules.
 */
class SubscriptionMvpBusinessLogicTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedReferenceData();
        $this->seed(SubscriptionPlanSeeder::class);
    }

    public function test_plan_without_roles_module_blocks_roles_api_with_required_modules(): void
    {
        $owner = $this->createOwnerUser(['email' => 'module.roles@test.com']);
        $basic = SubscriptionPlan::query()->where('slug', 'basic')->firstOrFail();

        app(SubscriptionEntitlements::class)->assignPlan($owner->saloon, $basic, startTrial: false);

        Sanctum::actingAs($owner->fresh());

        $this->getJson('/api/v1/roles?page=1&per_page=10')
            ->assertForbidden()
            ->assertJsonPath('required_modules.0', SubscriptionModules::ROLES);
    }

    public function test_plan_without_queue_module_blocks_live_queue_api(): void
    {
        $owner = $this->createOwnerUser(['email' => 'module.queue@test.com']);
        $this->assignDefaultSubscription($owner->saloon, 'free');

        Sanctum::actingAs($owner);

        $this->getJson('/api/v1/queue/live')
            ->assertForbidden()
            ->assertJsonPath('required_modules.0', SubscriptionModules::QUEUE);
    }

    public function test_basic_plan_includes_queue_module_and_allows_live_queue(): void
    {
        $owner = $this->createOwnerUser(['email' => 'module.queue.basic@test.com']);
        $basic = SubscriptionPlan::query()->where('slug', 'basic')->firstOrFail();

        app(SubscriptionEntitlements::class)->assignPlan($owner->saloon, $basic, startTrial: false);

        Sanctum::actingAs($owner);

        $this->getJson('/api/v1/me')
            ->assertOk();

        $modules = $this->getJson('/api/v1/me')->json('data.subscription_modules');
        $this->assertContains(SubscriptionModules::QUEUE, $modules);

        $this->getJson('/api/v1/queue/live')->assertOk();
    }

    public function test_owner_on_free_plan_can_access_appointments_but_staff_cannot_access_billing(): void
    {
        $owner = $this->createOwnerUser(['email' => 'free.owner@test.com']);
        $this->assignDefaultSubscription($owner->saloon, 'free');

        Sanctum::actingAs($owner);

        $this->getJson('/api/v1/appointments?page=1&per_page=10')->assertOk();
        $this->getJson('/api/v1/subscription')->assertOk();
        $this->getJson('/api/v1/billing/config')->assertOk();

        $staff = $this->createStaffUser([
            'email' => 'free.staff@test.com',
            'phone' => '+917210000101',
            'saloon' => $owner->saloon,
        ]);

        Sanctum::actingAs($staff);

        $this->getJson('/api/v1/subscription')
            ->assertForbidden()
            ->assertJsonPath('required_permissions.0', 'settings.view');
    }

    public function test_second_pending_checkout_is_rejected(): void
    {
        config([
            'payment.driver' => 'manual',
            'payment.allow_instant_upgrade' => false,
        ]);

        $owner = $this->actingAsOwner(['email' => 'double.checkout@test.com']);
        $pro = SubscriptionPlan::query()->where('slug', 'pro')->firstOrFail();

        $this->postJson('/api/v1/subscription/checkout', [
            'subscription_plan_id' => $pro->id,
            'notes' => 'First request',
        ])->assertCreated();

        $this->postJson('/api/v1/subscription/checkout', [
            'subscription_plan_id' => $pro->id,
            'notes' => 'Second request',
        ])->assertStatus(422);
    }

    public function test_admin_can_reject_pending_manual_upgrade(): void
    {
        config([
            'payment.driver' => 'manual',
            'payment.allow_instant_upgrade' => false,
        ]);

        $owner = $this->createOwnerUser(['email' => 'reject.upgrade@test.com']);
        $basic = SubscriptionPlan::query()->where('slug', 'basic')->firstOrFail();
        $pro = SubscriptionPlan::query()->where('slug', 'pro')->firstOrFail();

        $order = SubscriptionUpgradeOrder::query()->create([
            'saloon_id' => $owner->saloon_id,
            'requested_by_user_id' => $owner->id,
            'from_subscription_plan_id' => $basic->id,
            'to_subscription_plan_id' => $pro->id,
            'amount' => 200,
            'currency' => 'INR',
            'channel' => SubscriptionUpgradeOrder::CHANNEL_MANUAL,
            'status' => SubscriptionUpgradeOrder::STATUS_PENDING,
        ]);

        $this->actingAsSystemAdmin(['email' => 'reject.admin@test.com']);

        $this->postJson("/api/v1/admin/subscription-upgrades/{$order->id}/reject", [
            'reason' => 'Payment not received',
        ])
            ->assertOk()
            ->assertJsonPath('data.subscription_upgrade_order.status', SubscriptionUpgradeOrder::STATUS_REJECTED);

        Sanctum::actingAs($owner);

        $this->getJson('/api/v1/subscription')
            ->assertOk()
            ->assertJsonPath('data.plan.slug', 'free');
    }

    public function test_me_payload_includes_subscription_modules_and_limits_for_owner(): void
    {
        $owner = $this->createOwnerUser(['email' => 'me.sub@test.com']);
        $this->assignDefaultSubscription($owner->saloon, 'pro');

        Sanctum::actingAs($owner);

        $this->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.workspace', 'tenant')
            ->assertJsonStructure([
                'data' => [
                    'subscription_modules',
                    'subscription_limits',
                ],
            ]);

        $modules = $this->getJson('/api/v1/me')->json('data.subscription_modules');
        $this->assertContains(SubscriptionModules::ANALYTICS, $modules);
        $this->assertContains(SubscriptionModules::APPOINTMENTS, $modules);
    }

    public function test_branch_manager_cannot_upgrade_subscription(): void
    {
        $owner = $this->createOwnerUser(['email' => 'mgr.sub.owner@test.com']);
        $this->assignDefaultSubscription($owner->saloon, 'pro');

        $branch = \App\Models\SaloonBranch::query()->create([
            'saloon_id' => $owner->saloon_id,
            'branch_name' => 'Main',
            'business_address_1' => '1 Road',
            'city' => 'Mumbai',
            'state' => 'MH',
            'area_pincode' => '400001',
            'country' => 'India',
            'is_active' => true,
        ]);

        $managerRole = \App\Models\Role::findByCode(\App\Support\Role\RoleCodes::SALON_BRANCH_MANAGER);
        $manager = \App\Models\User::factory()->create([
            'email' => 'mgr.sub@test.com',
            'phone' => '+917210000202',
            'role_id' => $managerRole->id,
            'saloon_id' => $owner->saloon_id,
            'branch_id' => $branch->id,
            'is_active' => true,
            'onboarding_completed_at' => now(),
        ]);

        Sanctum::actingAs($manager);

        $pro = SubscriptionPlan::query()->where('slug', 'enterprise')->firstOrFail();

        $this->postJson('/api/v1/subscription/checkout', [
            'subscription_plan_id' => $pro->id,
        ])->assertForbidden();
    }

    public function test_approving_upgrade_for_attributed_salon_creates_renewal_commission(): void
    {
        config([
            'payment.driver' => 'manual',
            'payment.allow_instant_upgrade' => false,
        ]);

        $affiliate = $this->createAffiliatePartnerUser(['email' => 'renewal.aff@test.com']);
        $owner = $this->createOwnerUser(['email' => 'renewal.owner@test.com']);
        $this->assignDefaultSubscription($owner->saloon, 'basic');

        $owner->saloon()->update([
            'affiliate_partner_id' => $affiliate->affiliatePartner->id,
            'affiliate_referral_code' => $affiliate->affiliatePartner->code,
            'affiliate_attributed_at' => now(),
        ]);

        $basic = SubscriptionPlan::query()->where('slug', 'basic')->firstOrFail();
        $pro = SubscriptionPlan::query()->where('slug', 'pro')->firstOrFail();

        $order = SubscriptionUpgradeOrder::query()->create([
            'saloon_id' => $owner->saloon_id,
            'requested_by_user_id' => $owner->id,
            'from_subscription_plan_id' => $basic->id,
            'to_subscription_plan_id' => $pro->id,
            'amount' => 200,
            'currency' => 'INR',
            'channel' => SubscriptionUpgradeOrder::CHANNEL_MANUAL,
            'status' => SubscriptionUpgradeOrder::STATUS_PENDING,
        ]);

        $this->actingAsSystemAdmin(['email' => 'renewal.admin@test.com']);

        $this->postJson("/api/v1/admin/subscription-upgrades/{$order->id}/approve", [
            'payment_type' => 'upi',
            'amount' => 200,
            'transaction_id' => 'UPI-RENEW-1',
        ])->assertOk();

        $this->assertDatabaseHas('affiliate_commissions', [
            'affiliate_partner_id' => $affiliate->affiliatePartner->id,
            'saloon_id' => $owner->saloon_id,
            'subscription_upgrade_order_id' => $order->id,
            'type' => \App\Models\AffiliateCommission::TYPE_RENEWAL,
        ]);
    }
}
