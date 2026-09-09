<?php

namespace Tests\Feature\Affiliate;

use App\Models\AffiliateCommission;
use App\Models\AffiliatePartner;
use App\Models\AffiliateReferral;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AffiliatePortalApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedReferenceData();
    }

    public function test_affiliate_login_returns_affiliate_workspace(): void
    {
        $affiliate = $this->createAffiliatePartnerUser([
            'email' => 'login.affiliate@example.com',
            'password' => 'Password@123',
        ]);

        $this->postJson('/api/v1/public/login', [
            'login' => 'login.affiliate@example.com',
            'password' => 'Password@123',
            'device_name' => 'web',
        ])
            ->assertOk()
            ->assertJsonPath('data.workspace', 'affiliate')
            ->assertJsonPath('data.should_onboard', false)
            ->assertJsonPath('data.role.scope', 'affiliate')
            ->assertJsonPath('data.affiliate_partner.code', $affiliate->affiliatePartner->code)
            ->assertJsonFragment(['affiliate.dashboard.view']);
    }

    public function test_registration_with_affiliate_code_tracks_referral_and_onboarding_commission(): void
    {
        $this->seed(SubscriptionPlanSeeder::class);

        $affiliate = $this->createAffiliatePartnerUser([
            'email' => 'affiliate@example.com',
        ]);

        $response = $this->postJson('/api/v1/public/register', [
            'salon_name' => 'Referred Salon',
            'name' => 'Salon Owner',
            'email' => 'owner@example.com',
            'phone' => '+917100000001',
            'affiliate_code' => $affiliate->affiliatePartner->code,
            'password' => 'Password1',
            'password_confirmation' => 'Password1',
            'terms' => true,
        ]);

        $response->assertCreated();

        $saloonId = (int) $response->json('data.saloon.id');

        $this->assertDatabaseHas('saloons', [
            'id' => $saloonId,
            'affiliate_partner_id' => $affiliate->affiliatePartner->id,
            'affiliate_referral_code' => $affiliate->affiliatePartner->code,
        ]);

        $this->assertDatabaseHas('affiliate_referrals', [
            'saloon_id' => $saloonId,
            'affiliate_partner_id' => $affiliate->affiliatePartner->id,
            'source' => AffiliateReferral::SOURCE_LINK,
        ]);

        $this->assertDatabaseHas('affiliate_commissions', [
            'saloon_id' => $saloonId,
            'affiliate_partner_id' => $affiliate->affiliatePartner->id,
            'type' => AffiliateCommission::TYPE_ONBOARDING,
            'status' => AffiliateCommission::STATUS_LOCKED,
        ]);
    }

    public function test_affiliate_dashboard_and_withdrawal_request_flow_work(): void
    {
        $this->seed(SubscriptionPlanSeeder::class);

        $affiliate = $this->actingAsAffiliatePartner([
            'email' => 'portal.affiliate@example.com',
        ]);

        $owner = $this->createOwnerUser([
            'email' => 'referred.owner@example.com',
            'phone' => '+917200000001',
        ]);

        $owner->saloon()->update([
            'affiliate_partner_id' => $affiliate->affiliatePartner->id,
            'affiliate_referral_code' => $affiliate->affiliatePartner->code,
            'affiliate_attributed_at' => now(),
        ]);

        AffiliateReferral::query()->create([
            'affiliate_partner_id' => $affiliate->affiliatePartner->id,
            'saloon_id' => $owner->saloon_id,
            'owner_user_id' => $owner->id,
            'referral_code' => $affiliate->affiliatePartner->code,
            'source' => AffiliateReferral::SOURCE_LINK,
            'referred_at' => now()->subMonth(),
            'converted_at' => now()->subMonth(),
        ]);

        AffiliateCommission::query()->create([
            'affiliate_partner_id' => $affiliate->affiliatePartner->id,
            'saloon_id' => $owner->saloon_id,
            'type' => AffiliateCommission::TYPE_RENEWAL,
            'status' => AffiliateCommission::STATUS_LOCKED,
            'currency' => 'INR',
            'base_amount' => 1000,
            'commission_rate' => 5,
            'commission_amount' => 50,
            'locked_until' => now()->subDay(),
        ]);

        $this->getJson('/api/v1/affiliate/dashboard')
            ->assertOk()
            ->assertJsonPath('data.summary.available_commission', 50);

        $this->postJson('/api/v1/affiliate/withdrawals', [
            'amount' => 50,
            'notes' => 'Please transfer this payout.',
        ])->assertCreated();

        $this->assertDatabaseHas('affiliate_withdrawal_requests', [
            'affiliate_partner_id' => $affiliate->affiliatePartner->id,
            'amount' => 50,
            'status' => 'pending',
        ]);

        $this->assertDatabaseHas('affiliate_commissions', [
            'affiliate_partner_id' => $affiliate->affiliatePartner->id,
            'status' => AffiliateCommission::STATUS_REQUESTED,
        ]);

        $withdrawalId = (int) \App\Models\AffiliateWithdrawalRequest::query()
            ->where('affiliate_partner_id', $affiliate->affiliatePartner->id)
            ->value('id');

        $this->assertDatabaseHas('affiliate_commissions', [
            'affiliate_partner_id' => $affiliate->affiliatePartner->id,
            'affiliate_withdrawal_request_id' => $withdrawalId,
            'status' => AffiliateCommission::STATUS_REQUESTED,
        ]);

        $this->actingAsSystemAdmin(['email' => 'admin.affiliate.payout@example.com']);

        $this->postJson("/api/v1/admin/affiliate-withdrawals/{$withdrawalId}/approve")
            ->assertOk()
            ->assertJsonPath('data.withdrawal.status', 'approved');

        $this->postJson("/api/v1/admin/affiliate-withdrawals/{$withdrawalId}/mark-paid", [
            'payout_reference' => 'NEFT-AFF-001',
        ])
            ->assertOk()
            ->assertJsonPath('data.withdrawal.status', 'paid');

        $this->assertDatabaseHas('affiliate_commissions', [
            'affiliate_withdrawal_request_id' => $withdrawalId,
            'status' => AffiliateCommission::STATUS_PAID,
        ]);
    }

    public function test_admin_can_create_affiliate_partner(): void
    {
        $this->actingAsSystemAdmin(['email' => 'admin.create.affiliate@example.com']);

        $this->postJson('/api/v1/admin/affiliates', [
            'name' => 'Growth Partner',
            'email' => 'growth.partner@example.com',
            'phone' => '+917300000001',
            'password' => 'Password1',
            'password_confirmation' => 'Password1',
            'display_name' => 'Growth Partner Agency',
            'onboarding_commission_rate' => 12,
            'renewal_commission_rate' => 4,
            'commission_lock_days' => 45,
            'status' => 'active',
        ])
            ->assertCreated()
            ->assertJsonPath('data.affiliate_partner.renewal_commission_rate', 4)
            ->assertJsonPath('data.affiliate_partner.commission_lock_days', 45);

        $this->assertDatabaseHas('users', [
            'email' => 'growth.partner@example.com',
        ]);
    }

    public function test_affiliate_reports_endpoint_returns_summary(): void
    {
        $this->seed(SubscriptionPlanSeeder::class);

        $affiliate = $this->actingAsAffiliatePartner([
            'email' => 'reports.affiliate@example.com',
        ]);

        $owner = $this->createOwnerUser([
            'email' => 'reports.owner@example.com',
            'phone' => '+917400000001',
        ]);

        $owner->saloon()->update([
            'affiliate_partner_id' => $affiliate->affiliatePartner->id,
            'affiliate_referral_code' => $affiliate->affiliatePartner->code,
            'affiliate_attributed_at' => now(),
        ]);

        AffiliateReferral::query()->create([
            'affiliate_partner_id' => $affiliate->affiliatePartner->id,
            'saloon_id' => $owner->saloon_id,
            'owner_user_id' => $owner->id,
            'referral_code' => $affiliate->affiliatePartner->code,
            'source' => AffiliateReferral::SOURCE_LINK,
            'referred_at' => now(),
            'converted_at' => now(),
        ]);

        AffiliateCommission::query()->create([
            'affiliate_partner_id' => $affiliate->affiliatePartner->id,
            'saloon_id' => $owner->saloon_id,
            'type' => AffiliateCommission::TYPE_ONBOARDING,
            'status' => AffiliateCommission::STATUS_LOCKED,
            'currency' => 'INR',
            'base_amount' => 1000,
            'commission_rate' => 12,
            'commission_amount' => 120,
            'locked_until' => now()->addDays(30),
        ]);

        $this->getJson('/api/v1/affiliate/reports')
            ->assertOk()
            ->assertJsonPath('data.totals.commission_count', 1)
            ->assertJsonPath('data.by_type.onboarding.amount', 120);
    }

    public function test_admin_onboarding_can_attribute_affiliate_partner(): void
    {
        $this->seed(SubscriptionPlanSeeder::class);

        $affiliate = $this->createAffiliatePartnerUser([
            'email' => 'admin.attr.affiliate@example.com',
        ]);

        $this->actingAsSystemAdmin(['email' => 'admin.attr@example.com']);

        $planId = \App\Models\SubscriptionPlan::query()->where('slug', 'basic')->value('id')
            ?? \App\Models\SubscriptionPlan::query()->value('id');

        $response = $this->postJson('/api/v1/admin/onboarding', [
            'affiliate_partner_id' => $affiliate->affiliatePartner->id,
            'subscription_plan_id' => $planId,
            'start_trial' => false,
            'trial_days' => 0,
            'saloon' => [
                'business_name' => 'Admin Referred Salon',
                'payment_type' => 'Monthly',
                'payment_amount' => 1500,
                'transaction_id' => 'TXN-AFF-1',
                'is_active' => true,
            ],
            'branch' => [
                'branch_name' => 'Main',
                'business_address_1' => '1 Test Street',
                'city' => 'Mumbai',
                'state' => 'MH',
                'area_pincode' => '400001',
                'country' => 'India',
                'is_active' => true,
            ],
            'user' => [
                'firstname' => 'Admin',
                'lastname' => 'Owner',
                'email' => 'admin.referred.owner@example.com',
                'phone' => '9800011223',
                'password' => 'Password@123',
                'password_confirmation' => 'Password@123',
                'is_active' => true,
            ],
            'service_products' => [],
        ]);

        $response->assertCreated();

        $saloonId = (int) $response->json('data.saloon.id');

        $this->assertDatabaseHas('saloons', [
            'id' => $saloonId,
            'affiliate_partner_id' => $affiliate->affiliatePartner->id,
        ]);

        $this->assertDatabaseHas('affiliate_referrals', [
            'saloon_id' => $saloonId,
            'source' => AffiliateReferral::SOURCE_ADMIN,
        ]);

        $this->assertDatabaseHas('affiliate_commissions', [
            'saloon_id' => $saloonId,
            'type' => AffiliateCommission::TYPE_ONBOARDING,
            'base_amount' => 1500,
            'commission_amount' => 180,
        ]);

        $this->assertDatabaseHas('users', [
            'saloon_id' => $saloonId,
            'firstname' => 'Default',
            'lastname' => 'Staff',
        ]);

        $this->assertDatabaseHas('saloon_subscriptions', [
            'saloon_id' => $saloonId,
        ]);
    }

    public function test_public_affiliate_apply_creates_pending_partner(): void
    {
        $response = $this->postJson('/api/v1/public/affiliate/apply', [
            'name' => 'Growth Partner',
            'email' => 'apply.affiliate@example.com',
            'phone' => '+917500000001',
            'display_name' => 'Growth Co',
            'password' => 'Password@123',
            'password_confirmation' => 'Password@123',
            'payout_method' => 'bank_transfer',
            'notes' => 'Looking to refer salons in Mumbai.',
            'terms' => true,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.affiliate_partner.status', AffiliatePartner::STATUS_PENDING)
            ->assertJsonPath('data.affiliate_partner.display_name', 'Growth Co');

        $this->assertDatabaseHas('users', [
            'email' => 'apply.affiliate@example.com',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('affiliate_partners', [
            'display_name' => 'Growth Co',
            'status' => AffiliatePartner::STATUS_PENDING,
        ]);
    }

    public function test_pending_affiliate_cannot_access_portal_until_approved(): void
    {
        $this->postJson('/api/v1/public/affiliate/apply', [
            'name' => 'Pending Partner',
            'email' => 'pending.affiliate@example.com',
            'phone' => '+917500000002',
            'password' => 'Password@123',
            'password_confirmation' => 'Password@123',
            'terms' => true,
        ])->assertCreated();

        $partner = AffiliatePartner::query()
            ->whereHas('user', fn ($q) => $q->where('email', 'pending.affiliate@example.com'))
            ->firstOrFail();

        $affiliateUser = $partner->user;
        Sanctum::actingAs($affiliateUser);

        $this->getJson('/api/v1/affiliate/dashboard')
            ->assertForbidden()
            ->assertJsonPath('affiliate_status', AffiliatePartner::STATUS_PENDING);

        $this->actingAsSystemAdmin(['email' => 'approve.admin@example.com']);

        $this->postJson("/api/v1/admin/affiliates/{$partner->id}/approve")
            ->assertOk()
            ->assertJsonPath('data.affiliate_partner.status', AffiliatePartner::STATUS_ACTIVE);

        Sanctum::actingAs($affiliateUser->fresh(['affiliatePartner', 'role']));

        $this->getJson('/api/v1/affiliate/dashboard')
            ->assertOk();
    }

    public function test_admin_can_reject_pending_affiliate_application(): void
    {
        $this->postJson('/api/v1/public/affiliate/apply', [
            'name' => 'Reject Partner',
            'email' => 'reject.affiliate@example.com',
            'phone' => '+917500000003',
            'password' => 'Password@123',
            'password_confirmation' => 'Password@123',
            'terms' => true,
        ])->assertCreated();

        $partnerId = (int) AffiliatePartner::query()
            ->whereHas('user', fn ($q) => $q->where('email', 'reject.affiliate@example.com'))
            ->value('id');

        $this->actingAsSystemAdmin(['email' => 'reject.admin@example.com']);

        $this->postJson("/api/v1/admin/affiliates/{$partnerId}/reject", [
            'reason' => 'Incomplete profile',
        ])
            ->assertOk()
            ->assertJsonPath('data.affiliate_partner.status', AffiliatePartner::STATUS_REJECTED);

        $this->assertDatabaseHas('users', [
            'email' => 'reject.affiliate@example.com',
            'is_active' => false,
        ]);
    }
}
