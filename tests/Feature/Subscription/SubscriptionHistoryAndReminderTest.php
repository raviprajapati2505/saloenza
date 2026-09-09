<?php

namespace Tests\Feature\Subscription;

use App\Mail\SubscriptionRenewalReminderMail;
use App\Models\SaloonSubscription;
use App\Models\SaloonSubscriptionHistory;
use App\Models\SubscriptionPlan;
use App\Support\Subscription\SubscriptionEntitlements;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SubscriptionHistoryAndReminderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedReferenceData();
        $this->seed(SubscriptionPlanSeeder::class);
    }

    public function test_assign_plan_records_subscription_history_and_sets_ends_at(): void
    {
        $owner = $this->createOwnerUser(['email' => 'history.owner@test.com']);
        $basic = SubscriptionPlan::query()->where('slug', 'basic')->firstOrFail();

        $subscription = app(SubscriptionEntitlements::class)->assignPlan(
            $owner->saloon,
            $basic,
            startTrial: false,
            changedByUserId: $owner->id,
        );

        $this->assertNotNull($subscription->ends_at);
        $this->assertTrue($subscription->ends_at->greaterThan(now()->addDays(20)));

        $this->assertDatabaseHas('saloon_subscription_histories', [
            'saloon_id' => $owner->saloon_id,
            'saloon_subscription_id' => $subscription->id,
            'subscription_plan_id' => $basic->id,
            'action' => SaloonSubscriptionHistory::ACTION_ASSIGNED,
            'status' => SaloonSubscription::STATUS_ACTIVE,
        ]);
    }

    public function test_subscription_history_listing_is_super_admin_only(): void
    {
        $owner = $this->createOwnerUser(['email' => 'history.forbidden@test.com']);
        $basic = SubscriptionPlan::query()->where('slug', 'basic')->firstOrFail();
        app(SubscriptionEntitlements::class)->assignPlan($owner->saloon, $basic);

        Sanctum::actingAs($owner);
        $this->getJson('/api/v1/admin/subscription-history?page=1&per_page=10')
            ->assertForbidden();

        $admin = $this->actingAsSystemAdmin(['email' => 'history.admin@test.com']);
        $this->getJson('/api/v1/admin/subscription-history?page=1&per_page=10')
            ->assertOk()
            ->assertJsonPath('message', 'Subscription history fetched successfully.')
            ->assertJsonStructure([
                'data' => [
                    'subscription_histories',
                    'meta' => ['current_page', 'per_page', 'total', 'last_page'],
                ],
            ]);

        $this->assertNotNull($admin->fresh());
    }

    public function test_renewal_reminder_command_uses_plan_based_day_windows(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-01-01 10:00:00'));
        Mail::fake();

        $owner = $this->createOwnerUser(['email' => 'renewal.owner@test.com']);
        $basic = SubscriptionPlan::query()->where('slug', 'basic')->firstOrFail();
        $yearly = SubscriptionPlan::query()->where('slug', 'enterprise')->firstOrFail();
        $yearly->update(['billing_interval' => 'yearly', 'price' => 1000]);

        $subscription = app(SubscriptionEntitlements::class)->assignPlan(
            $owner->saloon,
            $basic,
            startTrial: false,
        );
        $subscription->update([
            'status' => SaloonSubscription::STATUS_ACTIVE,
            'ends_at' => now()->copy()->addDays(15)->startOfDay()->addHours(12),
            'trial_ends_at' => null,
            'renewal_reminder_sent_at' => null,
            'renewal_reminder_days_sent' => null,
        ]);

        Artisan::call('subscriptions:send-renewal-reminders');

        Mail::assertSent(SubscriptionRenewalReminderMail::class, function (SubscriptionRenewalReminderMail $mail) use ($owner): bool {
            return $mail->hasTo($owner->email) && $mail->daysRemaining === 15;
        });
        $this->assertEquals([15], $subscription->fresh()->renewal_reminder_days_sent);

        app(SubscriptionEntitlements::class)->assignPlan(
            $owner->saloon,
            $yearly,
            startTrial: false,
        )->update([
            'status' => SaloonSubscription::STATUS_ACTIVE,
            'ends_at' => now()->copy()->addDays(30)->startOfDay()->addHours(12),
            'trial_ends_at' => null,
            'renewal_reminder_sent_at' => null,
            'renewal_reminder_days_sent' => null,
        ]);

        Mail::fake();
        Artisan::call('subscriptions:send-renewal-reminders');
        Mail::assertSent(SubscriptionRenewalReminderMail::class, function (SubscriptionRenewalReminderMail $mail) use ($owner): bool {
            return $mail->hasTo($owner->email) && $mail->daysRemaining === 30;
        });

        Carbon::setTestNow();
    }

    public function test_renewal_reminder_skips_free_plans(): void
    {
        Mail::fake();

        $owner = $this->createOwnerUser(['email' => 'free.renewal@test.com']);
        $free = SubscriptionPlan::query()->where('slug', 'free')->firstOrFail();

        $subscription = app(SubscriptionEntitlements::class)->assignPlan($owner->saloon, $free);
        $subscription->update([
            'ends_at' => now()->addDays(15)->startOfDay()->addHours(12),
            'renewal_reminder_sent_at' => null,
            'renewal_reminder_days_sent' => null,
        ]);

        Artisan::call('subscriptions:send-renewal-reminders');

        Mail::assertNothingSent();
    }
}
