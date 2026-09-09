<?php

namespace Tests\Feature\Subscription;

use App\Models\User;
use App\Support\Subscription\SubscriptionEntitlements;
use Database\Seeders\SalonBootstrapBackfillSeeder;
use Database\Seeders\SubscriptionDemoSeeder;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionBootstrapBackfillTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedReferenceData();
        $this->seed(SubscriptionPlanSeeder::class);
    }

    public function test_bootstrap_backfill_does_not_replace_expired_demo_subscriptions(): void
    {
        $this->seed(SalonBootstrapBackfillSeeder::class);
        $this->seed(SubscriptionDemoSeeder::class);

        $owner = User::query()
            ->where('email', SubscriptionDemoSeeder::PAID_EXPIRED_EMAIL)
            ->firstOrFail();

        $entitlements = app(SubscriptionEntitlements::class);
        $summary = $entitlements->salonSubscriptionSummary($owner->saloon);

        $this->assertSame('read_only', $summary['access_mode']);
        $this->assertSame('expired', $summary['lifecycle']);
        $this->assertSame('basic', $summary['plan']['slug'] ?? null);
        $this->assertLessThan(0, $summary['days_remaining']);
    }

    public function test_me_payload_for_paid_expired_demo_includes_plan_and_read_only_mode(): void
    {
        $this->seed(SubscriptionDemoSeeder::class);

        $owner = User::query()
            ->where('email', SubscriptionDemoSeeder::PAID_EXPIRED_EMAIL)
            ->firstOrFail();

        $this->actingAs($owner);

        $this->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.tenant.subscription_access_mode', SubscriptionEntitlements::ACCESS_READ_ONLY)
            ->assertJsonPath('data.tenant.plan.slug', 'basic')
            ->assertJsonPath('data.tenant.subscription_summary.lifecycle', 'expired')
            ->assertJsonPath('data.tenant.subscription.status', 'expired');

        $this->postJson('/api/v1/appointments', [
            'branch_id' => 1,
            'starts_at' => now()->addDay()->toIso8601String(),
            'services' => [],
        ])->assertForbidden()
            ->assertJsonPath('subscription_access_mode', SubscriptionEntitlements::ACCESS_READ_ONLY);
    }
}
