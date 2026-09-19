<?php

namespace Tests\Feature\Subscription;

use App\Models\SubscriptionPlan;
use App\Support\Subscription\SubscriptionEntitlements;
use App\Support\Subscription\SubscriptionModules;
use App\Support\Subscription\SubscriptionPlanCatalog;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SubscriptionPlanEntitlementConsistencyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedReferenceData();
        $this->seed(SubscriptionPlanSeeder::class);
    }

    public function test_seeded_plan_modules_match_catalog_exactly(): void
    {
        foreach (SubscriptionPlanCatalog::modulesBySlug() as $slug => $expectedModules) {
            $plan = SubscriptionPlan::query()->where('slug', $slug)->firstOrFail();

            $this->assertSame(
                array_values($expectedModules),
                $plan->moduleList(),
                "Plan [{$slug}] modules drifted from SubscriptionPlanCatalog.",
            );
        }
    }

    public function test_assigned_plan_entitlements_match_plan_modules_for_every_seeded_plan(): void
    {
        foreach (SubscriptionPlanCatalog::definitions() as $definition) {
            $slug = $definition['slug'];
            $owner = $this->createOwnerUser([
                'email' => "entitlement.{$slug}@example.com",
                'phone' => '+9171'.str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT),
            ]);

            $plan = SubscriptionPlan::query()->where('slug', $slug)->firstOrFail();
            app(SubscriptionEntitlements::class)->assignPlan($owner->saloon, $plan, startTrial: false);

            Sanctum::actingAs($owner->fresh());

            $modules = $this->getJson('/api/v1/me')
                ->assertOk()
                ->assertJsonPath('data.tenant.plan.slug', $slug)
                ->json('data.subscription_modules');

            sort($modules);
            $expected = $plan->moduleList();
            sort($expected);

            $this->assertSame(
                $expected,
                $modules,
                "Assigned plan [{$slug}] did not expose its modules via /me.",
            );

            foreach ($expected as $module) {
                $this->assertTrue(
                    app(SubscriptionEntitlements::class)->hasModule($owner->saloon->fresh(), $module),
                    "Plan [{$slug}] should grant module [{$module}].",
                );
            }

            foreach (array_diff(SubscriptionModules::all(), $expected) as $excluded) {
                $this->assertFalse(
                    app(SubscriptionEntitlements::class)->hasModule($owner->saloon->fresh(), $excluded),
                    "Plan [{$slug}] should not grant module [{$excluded}].",
                );
            }
        }
    }

    public function test_paid_plan_onboarding_trial_grants_selected_plan_modules(): void
    {
        foreach (['basic', 'pro', 'enterprise'] as $slug) {
            $owner = $this->createOwnerUser([
                'email' => "trial.{$slug}@example.com",
                'phone' => '+9172'.str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT),
                'onboarding_completed_at' => null,
            ]);
            Sanctum::actingAs($owner);

            $plan = SubscriptionPlan::query()->where('slug', $slug)->firstOrFail();

            $this->postJson('/api/v1/onboarding/account', [
                'name' => $owner->name,
                'subscription_plan_id' => $plan->id,
            ])->assertOk();

            $modules = $this->getJson('/api/v1/me')
                ->assertOk()
                ->assertJsonPath('data.tenant.plan.slug', $slug)
                ->assertJsonPath('data.tenant.activation_pending', false)
                ->json('data.subscription_modules');

            foreach ($plan->moduleList() as $module) {
                $this->assertContains(
                    $module,
                    $modules,
                    "Onboarding trial for [{$slug}] missing module [{$module}].",
                );
            }
        }
    }
}
