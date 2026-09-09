<?php

namespace Tests\Feature\Subscription;

use App\Models\SubscriptionPlan;
use App\Models\SubscriptionUpgradeOrder;
use App\Support\Subscription\SubscriptionModules;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionUpgradePaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedReferenceData();
        $this->seed(SubscriptionPlanSeeder::class);
    }

    public function test_tenant_can_submit_manual_upgrade_request_when_gateway_not_configured(): void
    {
        config([
            'payment.driver' => 'manual',
            'payment.allow_instant_upgrade' => false,
        ]);

        $owner = $this->actingAsOwner(['email' => 'manual.upgrade@gmail.com']);
        $proPlan = SubscriptionPlan::query()->where('slug', 'pro')->firstOrFail();

        $response = $this->postJson('/api/v1/subscription/checkout', [
            'subscription_plan_id' => $proPlan->id,
            'notes' => 'Please upgrade after UPI payment.',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.checkout.mode', 'manual')
            ->assertJsonPath('data.upgrade_order.status', SubscriptionUpgradeOrder::STATUS_PENDING);

        $this->assertDatabaseHas('subscription_upgrade_orders', [
            'saloon_id' => $owner->saloon_id,
            'to_subscription_plan_id' => $proPlan->id,
            'status' => SubscriptionUpgradeOrder::STATUS_PENDING,
            'channel' => SubscriptionUpgradeOrder::CHANNEL_MANUAL,
        ]);
    }

    public function test_platform_admin_can_approve_manual_upgrade_request(): void
    {
        config([
            'payment.driver' => 'manual',
            'payment.allow_instant_upgrade' => false,
        ]);

        $owner = $this->createOwnerUser(['email' => 'approve.owner@gmail.com']);
        $proPlan = SubscriptionPlan::query()->where('slug', 'pro')->firstOrFail();
        $basicPlan = SubscriptionPlan::query()->where('slug', 'basic')->firstOrFail();

        $order = SubscriptionUpgradeOrder::query()->create([
            'saloon_id' => $owner->saloon_id,
            'requested_by_user_id' => $owner->id,
            'from_subscription_plan_id' => $basicPlan->id,
            'to_subscription_plan_id' => $proPlan->id,
            'amount' => 1500,
            'currency' => 'INR',
            'channel' => SubscriptionUpgradeOrder::CHANNEL_MANUAL,
            'status' => SubscriptionUpgradeOrder::STATUS_PENDING,
        ]);

        $admin = $this->actingAsSystemAdmin(['email' => 'admin.approve@gmail.com']);

        $this->postJson("/api/v1/admin/subscription-upgrades/{$order->id}/approve", [
            'payment_type' => 'upi',
            'amount' => 1500,
            'transaction_id' => 'UPI123456',
            'notes' => 'Collected offline',
        ])
            ->assertOk()
            ->assertJsonPath('data.subscription_upgrade_order.status', SubscriptionUpgradeOrder::STATUS_COMPLETED);

        $this->getJson('/api/v1/admin/subscription-upgrades?page=1&per_page=10&status=completed')
            ->assertOk();

        $this->actingAs($owner);

        $this->getJson('/api/v1/subscription')
            ->assertOk()
            ->assertJsonPath('data.plan.slug', 'pro');
    }

    public function test_platform_admin_can_assign_subscription_manually(): void
    {
        config(['payment.driver' => 'manual']);

        $owner = $this->createOwnerUser(['email' => 'assign.owner@gmail.com']);
        $enterprisePlan = SubscriptionPlan::query()->where('slug', 'enterprise')->firstOrFail();

        $admin = $this->actingAsSystemAdmin(['email' => 'admin.assign@gmail.com']);

        $this->postJson("/api/v1/admin/saloons/{$owner->saloon_id}/subscription/assign", [
            'subscription_plan_id' => $enterprisePlan->id,
            'payment_type' => 'bank_transfer',
            'amount' => 4999,
            'transaction_id' => 'NEFT999',
            'notes' => 'Annual offline payment',
        ])
            ->assertOk()
            ->assertJsonPath('data.subscription_upgrade_order.status', SubscriptionUpgradeOrder::STATUS_COMPLETED);

        $this->actingAs($owner);

        $this->getJson('/api/v1/subscription')
            ->assertOk()
            ->assertJsonPath('data.plan.slug', 'enterprise');
    }

    public function test_billing_config_reports_manual_mode_when_gateway_not_configured(): void
    {
        config([
            'payment.driver' => 'manual',
            'payment.allow_instant_upgrade' => false,
        ]);

        $owner = $this->actingAsOwner(['email' => 'billing.config@gmail.com']);

        $this->getJson('/api/v1/billing/config')
            ->assertOk()
            ->assertJsonPath('data.billing.driver', 'manual')
            ->assertJsonPath('data.billing.gateway_configured', false)
            ->assertJsonPath('data.billing.manual_mode', true);
    }

    public function test_instant_upgrade_is_blocked_when_disabled(): void
    {
        config([
            'payment.driver' => 'manual',
            'payment.allow_instant_upgrade' => false,
        ]);

        $this->seed(SubscriptionPlanSeeder::class);
        $owner = $this->actingAsOwner(['email' => 'instant.blocked@gmail.com']);
        $proPlan = SubscriptionPlan::query()->where('slug', 'pro')->firstOrFail();

        $this->postJson('/api/v1/subscription/upgrade', [
            'subscription_plan_id' => $proPlan->id,
        ])->assertStatus(422);
    }

    public function test_billing_config_reports_stripe_when_configured(): void
    {
        config([
            'payment.driver' => 'stripe',
            'payment.offline_only' => false,
            'payment.stripe.secret_key' => 'sk_test_example',
            'payment.stripe.publishable_key' => 'pk_test_example',
        ]);

        $this->actingAsOwner(['email' => 'stripe.config@gmail.com']);

        $this->getJson('/api/v1/billing/config')
            ->assertOk()
            ->assertJsonPath('data.billing.driver', 'stripe')
            ->assertJsonPath('data.billing.gateway_configured', true)
            ->assertJsonPath('data.billing.stripe_publishable_key', 'pk_test_example');
    }

    public function test_razorpay_webhook_fulfills_awaiting_upgrade_order(): void
    {
        config([
            'payment.razorpay.webhook_secret' => 'whsec_test',
        ]);

        $owner = $this->createOwnerUser(['email' => 'webhook.owner@gmail.com']);
        $proPlan = SubscriptionPlan::query()->where('slug', 'pro')->firstOrFail();
        $basicPlan = SubscriptionPlan::query()->where('slug', 'basic')->firstOrFail();

        $order = SubscriptionUpgradeOrder::query()->create([
            'saloon_id' => $owner->saloon_id,
            'requested_by_user_id' => $owner->id,
            'from_subscription_plan_id' => $basicPlan->id,
            'to_subscription_plan_id' => $proPlan->id,
            'amount' => 1500,
            'currency' => 'INR',
            'channel' => SubscriptionUpgradeOrder::CHANNEL_RAZORPAY,
            'status' => SubscriptionUpgradeOrder::STATUS_AWAITING_PAYMENT,
            'gateway_order_id' => 'order_webhook_123',
        ]);

        $payload = json_encode([
            'event' => 'payment.captured',
            'payload' => [
                'payment' => [
                    'entity' => [
                        'id' => 'pay_webhook_123',
                        'order_id' => 'order_webhook_123',
                        'status' => 'captured',
                    ],
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $signature = hash_hmac('sha256', $payload, 'whsec_test');

        $this->call('POST', '/api/v1/webhooks/payment/razorpay', [], [], [], [
            'HTTP_X-Razorpay-Signature' => $signature,
            'CONTENT_TYPE' => 'application/json',
        ], $payload)->assertOk();

        $order->refresh();
        $this->assertSame(SubscriptionUpgradeOrder::STATUS_COMPLETED, $order->status);

        $this->actingAs($owner);
        $this->getJson('/api/v1/subscription')
            ->assertOk()
            ->assertJsonPath('data.plan.slug', 'pro');
    }

    public function test_stripe_webhook_fulfills_awaiting_upgrade_order(): void
    {
        config([
            'payment.stripe.webhook_secret' => 'whsec_stripe_test',
        ]);

        $owner = $this->createOwnerUser(['email' => 'stripe.webhook@gmail.com']);
        $proPlan = SubscriptionPlan::query()->where('slug', 'pro')->firstOrFail();
        $basicPlan = SubscriptionPlan::query()->where('slug', 'basic')->firstOrFail();

        $order = SubscriptionUpgradeOrder::query()->create([
            'saloon_id' => $owner->saloon_id,
            'requested_by_user_id' => $owner->id,
            'from_subscription_plan_id' => $basicPlan->id,
            'to_subscription_plan_id' => $proPlan->id,
            'amount' => 1500,
            'currency' => 'INR',
            'channel' => SubscriptionUpgradeOrder::CHANNEL_STRIPE,
            'status' => SubscriptionUpgradeOrder::STATUS_AWAITING_PAYMENT,
            'gateway_order_id' => 'cs_test_webhook_123',
        ]);

        $payload = json_encode([
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'id' => 'cs_test_webhook_123',
                    'payment_status' => 'paid',
                    'payment_intent' => 'pi_test_123',
                    'metadata' => [
                        'upgrade_order_id' => (string) $order->id,
                    ],
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $timestamp = time();
        $signedPayload = $timestamp.'.'.$payload;
        $signature = hash_hmac('sha256', $signedPayload, 'whsec_stripe_test');

        $this->call('POST', '/api/v1/webhooks/payment/stripe', [], [], [], [
            'HTTP_Stripe-Signature' => 't='.$timestamp.',v1='.$signature,
            'CONTENT_TYPE' => 'application/json',
        ], $payload)->assertOk();

        $order->refresh();
        $this->assertSame(SubscriptionUpgradeOrder::STATUS_COMPLETED, $order->status);
    }
}
