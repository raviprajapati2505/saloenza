<?php

namespace Tests\Feature\Subscription;

use App\Models\AffiliateReferral;
use App\Support\Subscription\SubscriptionEntitlements;
use Database\Seeders\SubscriptionDemoSeeder;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionRenewalDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedReferenceData();
        $this->seed(SubscriptionPlanSeeder::class);
        $this->seed(SubscriptionDemoSeeder::class);
    }

    public function test_platform_admin_can_fetch_upcoming_subscription_renewals(): void
    {
        $this->actingAsSystemAdmin(['email' => 'renewals.admin@test.com']);

        $this->getJson('/api/v1/admin/subscription-renewals?days=30&limit=20')
            ->assertOk()
            ->assertJsonPath('message', 'Upcoming subscription renewals fetched successfully.')
            ->assertJsonStructure([
                'data' => [
                    'renewals',
                    'summary' => [
                        'active',
                        'expiring_total',
                        'expired',
                        'locked',
                        'total',
                    ],
                    'window_days',
                ],
            ]);
    }

    public function test_affiliate_referrals_include_subscription_summary_and_filter(): void
    {
        $affiliateUser = $this->actingAsAffiliatePartner(['email' => 'affiliate.renewals@test.com']);
        $partner = $affiliateUser->affiliatePartner;

        $paidExpiredOwner = \App\Models\User::query()
            ->where('email', SubscriptionDemoSeeder::PAID_EXPIRED_EMAIL)
            ->firstOrFail();

        $paidExpiredOwner->saloon->update([
            'affiliate_partner_id' => $partner->id,
            'affiliate_referral_code' => $partner->code,
        ]);

        AffiliateReferral::query()->updateOrCreate(
            [
                'affiliate_partner_id' => $partner->id,
                'saloon_id' => $paidExpiredOwner->saloon_id,
            ],
            [
                'owner_user_id' => $paidExpiredOwner->id,
                'referral_code' => $partner->code,
                'source' => 'link',
                'referred_at' => now(),
            ],
        );

        $this->getJson('/api/v1/affiliate/referrals?page=1&per_page=50')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'referrals',
                    'subscription_summary' => [
                        'active',
                        'expiring_total',
                        'expired',
                        'locked',
                        'total',
                    ],
                ],
            ])
            ->assertJsonFragment(['email' => SubscriptionDemoSeeder::PAID_EXPIRED_EMAIL]);

        $this->getJson('/api/v1/affiliate/referrals?subscription_lifecycle=expired&page=1&per_page=50')
            ->assertOk()
            ->assertJsonFragment(['email' => SubscriptionDemoSeeder::PAID_EXPIRED_EMAIL]);

        $this->getJson('/api/v1/affiliate/referrals?subscription_lifecycle=active&page=1&per_page=50')
            ->assertOk()
            ->assertJsonMissing(['email' => SubscriptionDemoSeeder::PAID_EXPIRED_EMAIL]);
    }

    public function test_salon_subscription_summary_reports_lifecycle_states(): void
    {
        $entitlements = app(SubscriptionEntitlements::class);

        $freeExpired = \App\Models\User::query()
            ->where('email', SubscriptionDemoSeeder::FREE_EXPIRED_EMAIL)
            ->firstOrFail();
        $paidExpiring = \App\Models\User::query()
            ->where('email', SubscriptionDemoSeeder::PAID_EXPIRING_EMAIL)
            ->firstOrFail();
        $freeActive = \App\Models\User::query()
            ->where('email', SubscriptionDemoSeeder::FREE_ACTIVE_EMAIL)
            ->firstOrFail();

        $this->assertSame('locked', $entitlements->salonSubscriptionSummary($freeExpired->saloon)['lifecycle']);

        $paidSummary = $entitlements->salonSubscriptionSummary($paidExpiring->saloon);
        $this->assertSame('expiring_critical', $paidSummary['lifecycle']);
        $this->assertSame(7, $paidSummary['days_remaining']);

        $this->assertSame('expiring_soon', $entitlements->salonSubscriptionSummary($freeActive->saloon)['lifecycle']);
    }
}
