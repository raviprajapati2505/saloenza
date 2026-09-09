<?php

namespace Tests\Feature\Referral;

use App\Models\SalonReferral;
use App\Models\SalonReferralCredit;
use App\Models\Saloon;
use App\Models\SaloonSubscription;
use App\Models\SubscriptionPlan;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SalonReferralPortalApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedReferenceData();
        $this->seed(SubscriptionPlanSeeder::class);
    }

    public function test_registration_with_referral_code_links_salon_referral(): void
    {
        $referrerSaloon = $this->createSaloon([
            'name' => 'Referrer Salon',
            'referral_code' => 'GLAM2025',
            'is_active' => true,
            'activation_status' => Saloon::ACTIVATION_ACTIVE,
        ]);
        $this->assignDefaultSubscription($referrerSaloon);

        $response = $this->postJson('/api/v1/public/register', [
            'salon_name' => 'Referred Salon',
            'name' => 'Referred Owner',
            'email' => 'referred.owner@example.com',
            'phone' => '+917100000010',
            'referral_code' => 'GLAM2025',
            'password' => 'Password1',
            'password_confirmation' => 'Password1',
            'terms' => true,
        ]);

        $response->assertCreated();

        $referredSaloonId = (int) $response->json('data.saloon.id');

        $this->assertDatabaseHas('saloons', [
            'id' => $referredSaloonId,
            'referrer_saloon_id' => $referrerSaloon->id,
        ]);

        $this->assertDatabaseHas('salon_referrals', [
            'referrer_saloon_id' => $referrerSaloon->id,
            'referred_saloon_id' => $referredSaloonId,
            'referral_code' => 'GLAM2025',
            'status' => SalonReferral::STATUS_IN_PROGRESS,
        ]);
    }

    public function test_referral_dashboard_returns_code_summary_and_referrals(): void
    {
        $owner = $this->createOwnerUser([
            'email' => 'referrer.owner@example.com',
        ]);
        $referrerSaloon = $owner->saloon;
        $referrerSaloon->update([
            'referral_code' => 'GLAM2025',
            'is_active' => true,
            'activation_status' => Saloon::ACTIVATION_ACTIVE,
        ]);

        $referredSaloon = $this->createSaloon([
            'name' => 'Style Haven',
            'referrer_saloon_id' => $referrerSaloon->id,
            'is_active' => true,
            'activation_status' => Saloon::ACTIVATION_ACTIVE,
        ]);
        $this->assignDefaultSubscription($referredSaloon);

        $referredOwner = $this->createOwnerUser([
            'email' => 'style.haven@example.com',
            'saloon_id' => $referredSaloon->id,
        ]);

        SalonReferral::query()->create([
            'referrer_saloon_id' => $referrerSaloon->id,
            'referred_saloon_id' => $referredSaloon->id,
            'owner_user_id' => $referredOwner->id,
            'referral_code' => 'GLAM2025',
            'source' => SalonReferral::SOURCE_LINK,
            'status' => SalonReferral::STATUS_IN_PROGRESS,
            'referred_at' => now()->subMonths(2),
        ]);

        Sanctum::actingAs($owner);

        $this->getJson('/api/v1/referrals/dashboard')
            ->assertOk()
            ->assertJsonPath('data.referral_code', 'GLAM2025')
            ->assertJsonPath('data.commission_rate', 10)
            ->assertJsonPath('data.qualifying_months', 6)
            ->assertJsonPath('data.summary.total_referrals', 1)
            ->assertJsonPath('data.summary.in_progress_count', 1)
            ->assertJsonPath('data.referrals.0.salon_name', 'Style Haven')
            ->assertJsonPath('data.referrals.0.status', SalonReferral::STATUS_IN_PROGRESS);
    }

    public function test_referral_qualifies_and_issues_credit_after_six_months(): void
    {
        $owner = $this->createOwnerUser([
            'email' => 'qualified.referrer@example.com',
        ]);
        $referrerSaloon = $owner->saloon;
        $referrerSaloon->update([
            'referral_code' => 'GLAM2026',
            'is_active' => true,
            'activation_status' => Saloon::ACTIVATION_ACTIVE,
        ]);

        $plan = SubscriptionPlan::query()->where('price', '>', 0)->first();

        if ($plan === null) {
            $plan = SubscriptionPlan::query()->create([
                'name' => 'Professional Plan',
                'slug' => 'professional-test',
                'price' => 1490,
                'billing_interval' => 'month',
                'trial_days' => 0,
                'modules' => [],
                'is_active' => true,
                'is_public' => true,
                'sort_order' => 99,
            ]);
        }

        $referredSaloon = $this->createSaloon([
            'name' => 'Qualified Salon',
            'referrer_saloon_id' => $referrerSaloon->id,
            'is_active' => true,
            'activation_status' => Saloon::ACTIVATION_ACTIVE,
        ]);

        SaloonSubscription::query()->create([
            'saloon_id' => $referredSaloon->id,
            'subscription_plan_id' => $plan->id,
            'status' => SaloonSubscription::STATUS_ACTIVE,
            'starts_at' => now()->subMonths(7),
            'ends_at' => now()->addMonth(),
        ]);

        $referral = SalonReferral::query()->create([
            'referrer_saloon_id' => $referrerSaloon->id,
            'referred_saloon_id' => $referredSaloon->id,
            'referral_code' => 'GLAM2026',
            'source' => SalonReferral::SOURCE_LINK,
            'status' => SalonReferral::STATUS_IN_PROGRESS,
            'referred_at' => now()->subMonths(7),
        ]);

        Sanctum::actingAs($owner);

        $this->getJson('/api/v1/referrals/dashboard')
            ->assertOk()
            ->assertJsonPath('data.summary.qualified_count', 1)
            ->assertJsonPath('data.summary.in_progress_count', 0)
            ->assertJsonPath('data.referrals.0.status', SalonReferral::STATUS_QUALIFIED)
            ->assertJsonPath('data.referrals.0.credit_label', 'Credited');

        $this->assertDatabaseHas('salon_referral_credits', [
            'referrer_saloon_id' => $referrerSaloon->id,
            'salon_referral_id' => $referral->id,
            'status' => SalonReferralCredit::STATUS_CREDITED,
        ]);
    }

    public function test_staff_without_permission_cannot_access_referral_dashboard(): void
    {
        $staff = $this->createStaffUser();
        Sanctum::actingAs($staff);

        $this->getJson('/api/v1/referrals/dashboard')
            ->assertForbidden();
    }

    public function test_public_registration_generates_referral_code_for_new_salon(): void
    {
        $response = $this->postJson('/api/v1/public/register', [
            'salon_name' => 'New Salon',
            'name' => 'Owner',
            'email' => 'new.owner@example.com',
            'phone' => '+917100000011',
            'password' => 'Password1',
            'password_confirmation' => 'Password1',
            'terms' => true,
        ]);

        $response->assertCreated();

        $saloonId = (int) $response->json('data.saloon.id');
        $saloon = Saloon::query()->findOrFail($saloonId);

        $this->assertNotNull($saloon->referral_code);
        $this->assertNotSame('', trim((string) $saloon->referral_code));
    }
}
