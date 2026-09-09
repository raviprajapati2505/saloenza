<?php

namespace Tests\Feature\Subscription;

use App\Models\Saloon;
use App\Models\SaloonSubscription;
use App\Models\SaloonSubscriptionHistory;
use App\Models\SubscriptionPlan;
use App\Support\Subscription\SubscriptionEntitlements;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Subscription lifecycle: free trial lock (Case 1) and paid expiry read-only (Case 2).
 */
class SubscriptionLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedReferenceData();
        $this->seed(SubscriptionPlanSeeder::class);
    }

    public function test_free_plan_defaults_to_thirty_day_trial(): void
    {
        $free = SubscriptionPlan::query()->where('slug', 'free')->firstOrFail();

        $this->assertSame(30, $free->trial_days);
    }

    public function test_expired_free_trial_locks_portal_but_allows_billing(): void
    {
        $owner = $this->createOwnerUser(['email' => 'free.expired@test.com']);
        $subscription = SaloonSubscription::query()
            ->where('saloon_id', $owner->saloon_id)
            ->firstOrFail();

        $subscription->update([
            'status' => SaloonSubscription::STATUS_TRIALING,
            'trial_ends_at' => now()->subDay(),
            'ends_at' => now()->subDay(),
        ]);

        Sanctum::actingAs($owner);

        $this->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.tenant.subscription_access_mode', SubscriptionEntitlements::ACCESS_LOCKED)
            ->assertJsonPath('data.tenant.subscription_expired', true);

        $this->getJson('/api/v1/appointments?page=1&per_page=10')
            ->assertForbidden()
            ->assertJsonPath('subscription_access_mode', SubscriptionEntitlements::ACCESS_LOCKED);

        $this->getJson('/api/v1/subscription')->assertOk();
        $this->getJson('/api/v1/billing/config')->assertOk();
        $this->getJson('/api/v1/subscription-plans?page=1&per_page=10')->assertOk();
    }

    public function test_activation_pending_salon_is_locked_on_operational_api(): void
    {
        $owner = $this->createOwnerUser(['email' => 'pending.lock@test.com']);
        $owner->saloon->markActivationPending();

        Sanctum::actingAs($owner);

        $this->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.tenant.activation_pending', true)
            ->assertJsonPath('data.tenant.subscription_access_mode', SubscriptionEntitlements::ACCESS_LOCKED);

        $this->getJson('/api/v1/appointments?page=1&per_page=10')
            ->assertForbidden()
            ->assertJsonPath('subscription_access_mode', SubscriptionEntitlements::ACCESS_LOCKED);
    }

    public function test_expired_paid_subscription_allows_view_but_blocks_mutations(): void
    {
        $owner = $this->createOwnerUser(['email' => 'paid.expired@test.com']);
        $basic = SubscriptionPlan::query()->where('slug', 'basic')->firstOrFail();

        $subscription = app(SubscriptionEntitlements::class)->assignPlan(
            $owner->saloon,
            $basic,
            startTrial: false,
        );
        $subscription->update([
            'status' => SaloonSubscription::STATUS_ACTIVE,
            'ends_at' => now()->subDay(),
            'trial_ends_at' => null,
        ]);

        Sanctum::actingAs($owner);

        $this->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.tenant.subscription_access_mode', SubscriptionEntitlements::ACCESS_READ_ONLY)
            ->assertJsonPath('data.tenant.subscription_expired', true)
            ->assertJsonPath('data.tenant.modules', $basic->moduleList());

        $this->getJson('/api/v1/appointments?page=1&per_page=10')->assertOk();

        $this->postJson('/api/v1/appointments', [
            'customer_id' => 1,
            'branch_id' => 1,
            'starts_at' => now()->addDay()->toIso8601String(),
            'services' => [],
        ])->assertForbidden()
            ->assertJsonPath('subscription_access_mode', SubscriptionEntitlements::ACCESS_READ_ONLY);

        $this->getJson('/api/v1/subscription')->assertOk();

        $this->putJson('/api/v1/profile', [
            'name' => 'Updated Name',
        ])->assertForbidden()
            ->assertJsonPath('subscription_access_mode', SubscriptionEntitlements::ACCESS_READ_ONLY);
    }

    public function test_renewing_paid_subscription_restores_full_access(): void
    {
        $owner = $this->createOwnerUser(['email' => 'paid.renew@test.com']);
        $basic = SubscriptionPlan::query()->where('slug', 'basic')->firstOrFail();
        $entitlements = app(SubscriptionEntitlements::class);

        $subscription = $entitlements->assignPlan($owner->saloon, $basic, startTrial: false);
        $subscription->update([
            'status' => SaloonSubscription::STATUS_EXPIRED,
            'ends_at' => now()->subDay(),
        ]);

        $this->assertSame(
            SubscriptionEntitlements::ACCESS_READ_ONLY,
            $entitlements->accessMode($owner->saloon->fresh()),
        );

        $entitlements->assignPlan($owner->saloon->fresh(), $basic, startTrial: false);

        $this->assertSame(
            SubscriptionEntitlements::ACCESS_FULL,
            $entitlements->accessMode($owner->saloon->fresh()),
        );

        Sanctum::actingAs($owner->fresh());

        $this->getJson('/api/v1/appointments?page=1&per_page=10')->assertOk();
    }

    public function test_expire_subscriptions_command_marks_past_due_rows_and_records_history(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-01 00:00:00'));

        $owner = $this->createOwnerUser(['email' => 'expire.cron@test.com']);
        $subscription = SaloonSubscription::query()
            ->where('saloon_id', $owner->saloon_id)
            ->firstOrFail();

        $subscription->update([
            'trial_ends_at' => Carbon::parse('2026-01-15'),
            'ends_at' => Carbon::parse('2026-01-15'),
        ]);

        Artisan::call('subscriptions:expire');

        $this->assertDatabaseHas('saloon_subscriptions', [
            'id' => $subscription->id,
            'status' => SaloonSubscription::STATUS_EXPIRED,
        ]);

        $this->assertDatabaseHas('saloon_subscription_histories', [
            'saloon_subscription_id' => $subscription->id,
            'action' => SaloonSubscriptionHistory::ACTION_EXPIRED,
        ]);

        Carbon::setTestNow();
    }
}
