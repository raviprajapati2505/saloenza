<?php

namespace Tests\Feature\Subscription;

use App\Models\Saloon;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionUpgradeOrder;
use App\Models\SaloonSubscription;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PaidPlanActivationRequestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedReferenceData();
        $this->seed(SubscriptionPlanSeeder::class);
    }

    public function test_paid_plan_onboarding_starts_trial_and_keeps_offline_request(): void
    {
        $owner = $this->createOwnerUser([
            'email' => 'paid.onboard@example.com',
            'phone' => '+917700000001',
            'onboarding_completed_at' => null,
        ]);

        $owner->saloon->markActivated();
        Sanctum::actingAs($owner);

        $basic = SubscriptionPlan::query()->where('slug', 'basic')->firstOrFail();

        $this->postJson('/api/v1/onboarding/account', [
            'name' => $owner->name,
            'subscription_plan_id' => $basic->id,
        ])->assertOk();

        $this->assertDatabaseHas('saloons', [
            'id' => $owner->saloon_id,
            'is_active' => true,
            'activation_status' => Saloon::ACTIVATION_ACTIVE,
        ]);

        $this->assertDatabaseHas('subscription_upgrade_orders', [
            'saloon_id' => $owner->saloon_id,
            'to_subscription_plan_id' => $basic->id,
            'channel' => SubscriptionUpgradeOrder::CHANNEL_MANUAL,
            'status' => SubscriptionUpgradeOrder::STATUS_PENDING,
        ]);

        $this->assertDatabaseHas('saloon_subscriptions', [
            'saloon_id' => $owner->saloon_id,
            'subscription_plan_id' => $basic->id,
            'status' => SaloonSubscription::STATUS_TRIALING,
        ]);

        $this->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.tenant.activation_pending', false)
            ->assertJsonPath('data.tenant.plan.slug', 'basic');

        $modules = $this->getJson('/api/v1/me')->json('data.subscription_modules');
        $this->assertContains('inventory', $modules);
    }

    public function test_admin_approval_activates_salon_and_assigns_paid_plan(): void
    {
        $owner = $this->createOwnerUser([
            'email' => 'paid.activate@example.com',
            'phone' => '+917700000002',
            'onboarding_completed_at' => null,
        ]);
        Sanctum::actingAs($owner);

        $basic = SubscriptionPlan::query()->where('slug', 'basic')->firstOrFail();

        $this->postJson('/api/v1/onboarding/account', [
            'name' => $owner->name,
            'subscription_plan_id' => $basic->id,
        ])->assertOk();

        $orderId = (int) SubscriptionUpgradeOrder::query()
            ->where('saloon_id', $owner->saloon_id)
            ->value('id');

        $this->actingAsSystemAdmin(['email' => 'activate.admin@example.com']);

        $this->postJson("/api/v1/admin/subscription-upgrades/{$orderId}/approve", [
            'payment_type' => 'upi',
            'transaction_id' => 'TXN-ACTIVATE-1',
            'amount' => 299,
        ])->assertOk();

        $this->assertDatabaseHas('saloons', [
            'id' => $owner->saloon_id,
            'is_active' => true,
            'activation_status' => Saloon::ACTIVATION_ACTIVE,
        ]);

        $this->assertDatabaseHas('saloon_subscriptions', [
            'saloon_id' => $owner->saloon_id,
            'subscription_plan_id' => $basic->id,
        ]);
    }

    public function test_free_plan_onboarding_keeps_salon_active(): void
    {
        $owner = $this->createOwnerUser([
            'email' => 'free.onboard@example.com',
            'phone' => '+917700000003',
            'onboarding_completed_at' => null,
        ]);
        Sanctum::actingAs($owner);

        $free = SubscriptionPlan::query()->where('slug', 'free')->firstOrFail();

        $this->postJson('/api/v1/onboarding/account', [
            'name' => $owner->name,
            'subscription_plan_id' => $free->id,
        ])->assertOk();

        $this->assertDatabaseHas('saloons', [
            'id' => $owner->saloon_id,
            'is_active' => true,
            'activation_status' => Saloon::ACTIVATION_ACTIVE,
        ]);
    }
}
