<?php

namespace Tests\Feature\Affiliate;

use App\Models\AffiliateCommission;
use App\Models\AffiliatePartner;
use App\Models\AffiliateReferral;
use App\Models\AffiliateWithdrawalRequest;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * MVP affiliate referral + withdrawal edge cases.
 */
class AffiliateReferralAndWithdrawalEdgeCasesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedReferenceData();
        $this->seed(SubscriptionPlanSeeder::class);
    }

    public function test_inactive_affiliate_code_does_not_attach_on_registration(): void
    {
        $affiliate = $this->createAffiliatePartnerUser(['email' => 'inactive.code@test.com']);
        $affiliate->affiliatePartner->update(['status' => AffiliatePartner::STATUS_SUSPENDED]);

        $response = $this->postJson('/api/v1/public/register', [
            'salon_name' => 'No Aff Salon',
            'name' => 'Owner',
            'email' => 'no.aff.owner@test.com',
            'phone' => '+917310000001',
            'affiliate_code' => $affiliate->affiliatePartner->code,
            'password' => 'Password1',
            'password_confirmation' => 'Password1',
            'terms' => true,
        ]);

        $response->assertCreated();

        $saloonId = (int) $response->json('data.saloon.id');

        $this->assertDatabaseHas('saloons', [
            'id' => $saloonId,
            'affiliate_partner_id' => null,
        ]);

        $this->assertDatabaseMissing('affiliate_referrals', [
            'saloon_id' => $saloonId,
        ]);
    }

    public function test_unknown_affiliate_code_does_not_block_registration(): void
    {
        $response = $this->postJson('/api/v1/public/register', [
            'salon_name' => 'Unknown Aff Salon',
            'name' => 'Owner Two',
            'email' => 'unknown.aff.owner@test.com',
            'phone' => '+917310000002',
            'affiliate_code' => 'DOES-NOT-EXIST',
            'password' => 'Password1',
            'password_confirmation' => 'Password1',
            'terms' => true,
        ]);

        $response->assertCreated();
        $this->assertNull($response->json('data.saloon.affiliate_partner_id'));
    }

    public function test_pending_affiliate_cannot_access_portal(): void
    {
        $affiliate = $this->createAffiliatePartnerUser(['email' => 'pending.aff@test.com']);
        $affiliate->affiliatePartner->update(['status' => AffiliatePartner::STATUS_PENDING]);
        $affiliate->forceFill(['is_active' => true])->save();

        Sanctum::actingAs($affiliate->fresh(['affiliatePartner', 'role']));

        $this->getJson('/api/v1/affiliate/dashboard')
            ->assertForbidden();
    }

    public function test_withdrawal_over_available_balance_is_rejected(): void
    {
        $affiliate = $this->actingAsAffiliatePartner(['email' => 'over.withdraw@test.com']);
        $owner = $this->createOwnerUser(['email' => 'over.withdraw.owner@test.com', 'phone' => '+917310000011']);

        AffiliateCommission::query()->create([
            'affiliate_partner_id' => $affiliate->affiliatePartner->id,
            'saloon_id' => $owner->saloon_id,
            'type' => AffiliateCommission::TYPE_ONBOARDING,
            'status' => AffiliateCommission::STATUS_AVAILABLE,
            'currency' => 'INR',
            'base_amount' => 1000,
            'commission_rate' => 10,
            'commission_amount' => 100,
            'available_at' => now(),
        ]);

        $this->postJson('/api/v1/affiliate/withdrawals', [
            'amount' => 500,
            'notes' => 'Too much',
        ])->assertStatus(422);
    }

    public function test_second_pending_withdrawal_is_rejected(): void
    {
        $affiliate = $this->actingAsAffiliatePartner(['email' => 'second.withdraw@test.com']);
        $owner = $this->createOwnerUser(['email' => 'second.withdraw.owner@test.com', 'phone' => '+917310000012']);

        AffiliateCommission::query()->create([
            'affiliate_partner_id' => $affiliate->affiliatePartner->id,
            'saloon_id' => $owner->saloon_id,
            'type' => AffiliateCommission::TYPE_ONBOARDING,
            'status' => AffiliateCommission::STATUS_AVAILABLE,
            'currency' => 'INR',
            'base_amount' => 2000,
            'commission_rate' => 10,
            'commission_amount' => 200,
            'available_at' => now(),
        ]);

        $this->postJson('/api/v1/affiliate/withdrawals', [
            'amount' => 100,
        ])->assertCreated();

        $this->assertSame(
            100.0,
            (float) AffiliateCommission::query()
                ->where('affiliate_partner_id', $affiliate->affiliatePartner->id)
                ->where('status', AffiliateCommission::STATUS_AVAILABLE)
                ->sum('commission_amount'),
        );

        $this->assertSame(
            100.0,
            (float) AffiliateCommission::query()
                ->where('affiliate_partner_id', $affiliate->affiliatePartner->id)
                ->where('status', AffiliateCommission::STATUS_REQUESTED)
                ->sum('commission_amount'),
        );

        $this->postJson('/api/v1/affiliate/withdrawals', [
            'amount' => 50,
        ])->assertStatus(422);
    }

    public function test_partial_withdrawal_splits_commission_and_keeps_remainder_available(): void
    {
        $affiliate = $this->actingAsAffiliatePartner(['email' => 'partial.withdraw@test.com']);
        $owner = $this->createOwnerUser(['email' => 'partial.withdraw.owner@test.com', 'phone' => '+917310000014']);

        $commission = AffiliateCommission::query()->create([
            'affiliate_partner_id' => $affiliate->affiliatePartner->id,
            'saloon_id' => $owner->saloon_id,
            'type' => AffiliateCommission::TYPE_ONBOARDING,
            'status' => AffiliateCommission::STATUS_AVAILABLE,
            'currency' => 'INR',
            'base_amount' => 1000,
            'commission_rate' => 20,
            'commission_amount' => 200,
            'available_at' => now(),
        ]);

        $this->postJson('/api/v1/affiliate/withdrawals', [
            'amount' => 75,
            'notes' => 'Partial payout',
        ])->assertCreated();

        $this->assertDatabaseHas('affiliate_commissions', [
            'id' => $commission->id,
            'status' => AffiliateCommission::STATUS_REQUESTED,
            'commission_amount' => 75,
            'base_amount' => 375,
        ]);

        $this->assertDatabaseHas('affiliate_commissions', [
            'affiliate_partner_id' => $affiliate->affiliatePartner->id,
            'saloon_id' => $owner->saloon_id,
            'status' => AffiliateCommission::STATUS_AVAILABLE,
            'commission_amount' => 125,
            'base_amount' => 625,
        ]);

        $this->getJson('/api/v1/affiliate/dashboard')
            ->assertOk()
            ->assertJsonPath('data.summary.available_commission', 125)
            ->assertJsonPath('data.summary.requested_commission', 75);
    }

    public function test_admin_reject_withdrawal_restores_commissions_to_available(): void
    {
        $affiliate = $this->actingAsAffiliatePartner(['email' => 'reject.withdraw@test.com']);
        $owner = $this->createOwnerUser(['email' => 'reject.withdraw.owner@test.com', 'phone' => '+917310000013']);

        $commission = AffiliateCommission::query()->create([
            'affiliate_partner_id' => $affiliate->affiliatePartner->id,
            'saloon_id' => $owner->saloon_id,
            'type' => AffiliateCommission::TYPE_ONBOARDING,
            'status' => AffiliateCommission::STATUS_AVAILABLE,
            'currency' => 'INR',
            'base_amount' => 1000,
            'commission_rate' => 10,
            'commission_amount' => 100,
            'available_at' => now(),
        ]);

        $this->postJson('/api/v1/affiliate/withdrawals', [
            'amount' => 100,
        ])->assertCreated();

        $withdrawalId = (int) AffiliateWithdrawalRequest::query()
            ->where('affiliate_partner_id', $affiliate->affiliatePartner->id)
            ->value('id');

        $this->assertDatabaseHas('affiliate_commissions', [
            'id' => $commission->id,
            'status' => AffiliateCommission::STATUS_REQUESTED,
        ]);

        $this->actingAsSystemAdmin(['email' => 'reject.withdraw.admin@test.com']);

        $this->postJson("/api/v1/admin/affiliate-withdrawals/{$withdrawalId}/reject", [
            'reason' => 'Invalid bank details',
        ])
            ->assertOk()
            ->assertJsonPath('data.withdrawal.status', 'rejected');

        $this->assertDatabaseHas('affiliate_commissions', [
            'id' => $commission->id,
            'status' => AffiliateCommission::STATUS_AVAILABLE,
            'affiliate_withdrawal_request_id' => null,
        ]);
    }

    public function test_affiliate_referrals_list_returns_attributed_salons(): void
    {
        $affiliate = $this->actingAsAffiliatePartner(['email' => 'referrals.list@test.com']);
        $owner = $this->createOwnerUser([
            'email' => 'referred.list@test.com',
            'phone' => '+917310000099',
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

        $this->getJson('/api/v1/affiliate/referrals?page=1&per_page=20')
            ->assertOk()
            ->assertJsonFragment(['referral_code' => $affiliate->affiliatePartner->code]);
    }

    public function test_staff_and_owner_cannot_access_affiliate_portal(): void
    {
        $owner = $this->actingAsOwner(['email' => 'owner.not.aff@test.com']);

        $this->getJson('/api/v1/affiliate/dashboard')->assertForbidden();

        $staff = $this->createStaffUser([
            'email' => 'staff.not.aff@test.com',
            'phone' => '+917310000088',
            'saloon' => $owner->saloon,
        ]);

        Sanctum::actingAs($staff);

        $this->getJson('/api/v1/affiliate/reports')->assertForbidden();
    }
}
