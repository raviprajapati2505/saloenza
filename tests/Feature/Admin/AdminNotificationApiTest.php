<?php

namespace Tests\Feature\Admin;

use App\Models\AffiliatePartner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminNotificationApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedReferenceData();
    }

    public function test_affiliate_application_notifies_platform_admin(): void
    {
        $admin = $this->createSystemAdmin([
            'email' => 'notify.admin@example.com',
        ]);

        $this->postJson('/api/v1/public/affiliate/apply', [
            'name' => 'Notify Partner',
            'email' => 'notify.partner@example.com',
            'phone' => '+917600000001',
            'password' => 'Password@123',
            'password_confirmation' => 'Password@123',
            'terms' => true,
        ])->assertCreated();

        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => $admin->getMorphClass(),
            'notifiable_id' => $admin->id,
        ]);

        $this->actingAsSystemAdmin(['email' => 'notify.admin@example.com']);

        $this->getJson('/api/v1/admin/notifications')
            ->assertOk()
            ->assertJsonPath('data.unread_count', 1)
            ->assertJsonPath('data.notifications.0.event', 'affiliate.partner.applied')
            ->assertJsonPath('data.notifications.0.action_url', '/admin/affiliates?tab=partners&status=pending');

        $this->getJson('/api/v1/admin/notifications/pending-counts')
            ->assertOk()
            ->assertJsonPath('data.counts.affiliate_applications', 1);

        $notificationId = $this->getJson('/api/v1/admin/notifications')->json('data.notifications.0.id');

        $this->postJson("/api/v1/admin/notifications/{$notificationId}/read")
            ->assertOk()
            ->assertJsonPath('data.unread_count', 0);

        $this->assertDatabaseHas('affiliate_partners', [
            'status' => AffiliatePartner::STATUS_PENDING,
        ]);
    }

    public function test_affiliate_withdrawal_request_creates_admin_notification(): void
    {
        $admin = $this->createSystemAdmin([
            'email' => 'withdraw.notify.admin@example.com',
        ]);

        $affiliate = $this->actingAsAffiliatePartner([
            'email' => 'withdraw.notify.partner@example.com',
        ]);

        $owner = $this->createOwnerUser([
            'email' => 'withdraw.notify.owner@example.com',
            'phone' => '+917600000002',
        ]);

        \App\Models\AffiliateCommission::query()->create([
            'affiliate_partner_id' => $affiliate->affiliatePartner->id,
            'saloon_id' => $owner->saloon_id,
            'type' => \App\Models\AffiliateCommission::TYPE_ONBOARDING,
            'status' => \App\Models\AffiliateCommission::STATUS_AVAILABLE,
            'currency' => 'INR',
            'base_amount' => 1000,
            'commission_rate' => 12,
            'commission_amount' => 120,
            'available_at' => now()->subDay(),
        ]);

        $this->postJson('/api/v1/affiliate/withdrawals', [
            'amount' => 120,
            'notes' => 'Need payout',
        ])->assertCreated();

        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $admin->id,
        ]);

        $this->actingAsSystemAdmin(['email' => 'withdraw.notify.admin@example.com']);

        $this->getJson('/api/v1/admin/notifications')
            ->assertOk()
            ->assertJsonPath('data.notifications.0.event', 'affiliate.withdrawal.requested')
            ->assertJsonPath('data.notifications.0.action_url', '/admin/affiliates?tab=withdrawals&status=pending');

        $this->assertDatabaseHas('affiliate_withdrawal_requests', [
            'affiliate_partner_id' => $affiliate->affiliatePartner->id,
            'amount' => 120,
            'status' => \App\Models\AffiliateWithdrawalRequest::STATUS_PENDING,
        ]);

        $this->assertSame(
            1,
            \App\Models\AffiliateWithdrawalRequest::query()
                ->where('affiliate_partner_id', $affiliate->affiliatePartner->id)
                ->count(),
        );

        $counts = $this->getJson('/api/v1/admin/notifications/pending-counts')
            ->assertOk()
            ->json('data.counts');

        $this->assertGreaterThanOrEqual(1, (int) ($counts['affiliate_withdrawals'] ?? 0));
    }

    public function test_tenant_cannot_access_admin_notifications(): void
    {
        $this->actingAsOwner(['phone' => '+917600000099']);

        $this->getJson('/api/v1/admin/notifications')
            ->assertForbidden();
    }

    public function test_mark_all_notifications_read_clears_unread_count(): void
    {
        $admin = $this->createSystemAdmin([
            'email' => 'markall.admin@example.com',
        ]);

        $this->postJson('/api/v1/public/affiliate/apply', [
            'name' => 'Mark All Partner',
            'email' => 'markall.partner@example.com',
            'phone' => '+917600000010',
            'password' => 'Password@123',
            'password_confirmation' => 'Password@123',
            'terms' => true,
        ])->assertCreated();

        $this->actingAsSystemAdmin(['email' => 'markall.admin@example.com']);

        $this->getJson('/api/v1/admin/notifications/unread-count')
            ->assertOk()
            ->assertJsonPath('data.unread_count', 1);

        $this->postJson('/api/v1/admin/notifications/read-all')
            ->assertOk()
            ->assertJsonPath('data.unread_count', 0);

        $this->assertNotNull(
            $admin->fresh()->notifications()->first()?->read_at
        );
    }
}
