<?php

namespace Tests\Feature\Tenant;

use App\Mail\SubscriptionRenewalReminderMail;
use App\Models\SalonSetting;
use App\Models\SaloonSubscription;
use App\Models\SubscriptionPlan;
use App\Services\Tenant\TenantMailConfigResolver;
use App\Support\Subscription\SubscriptionEntitlements;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class TenantNotificationDeliveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SubscriptionPlanSeeder::class);
    }

    public function test_mail_config_resolver_uses_brevo_when_api_key_is_set(): void
    {
        config()->set('services.brevo.key', 'xkeysib-test-key');
        config()->set('mail.default', 'log');
        config()->set('mail.from.address', 'brevo@saloenza.test');
        config()->set('mail.from.name', 'Saloenza Brevo');

        $owner = $this->createOwnerUser();
        $config = app(TenantMailConfigResolver::class)->resolve($owner->saloon);

        $this->assertSame('brevo', $config['mailer']);
        $this->assertNull($config['transport']);
        $this->assertSame('brevo@saloenza.test', $config['from']['address']);
        $this->assertSame('Saloenza Brevo', $config['from']['name']);
    }

    public function test_mail_config_resolver_falls_back_to_default_mailer_without_brevo_key(): void
    {
        config()->set('services.brevo.key', '');
        config()->set('mail.default', 'log');
        config()->set('mail.from.address', 'env@saloenza.test');

        $owner = $this->createOwnerUser();

        SalonSetting::query()->create([
            'saloon_id' => $owner->saloon_id,
            'group' => 'email',
            'key' => 'use_custom',
            'value' => true,
        ]);
        SalonSetting::query()->create([
            'saloon_id' => $owner->saloon_id,
            'group' => 'email',
            'key' => 'smtp_host',
            'value' => 'smtp.demo-salon.test',
        ]);
        SalonSetting::query()->create([
            'saloon_id' => $owner->saloon_id,
            'group' => 'email',
            'key' => 'from_email',
            'value' => 'hello@demo-salon.test',
        ]);

        \Illuminate\Support\Facades\Cache::forget('salon_settings.'.$owner->saloon_id);

        $config = app(TenantMailConfigResolver::class)->resolve($owner->saloon->fresh());

        $this->assertSame('log', $config['mailer']);
        $this->assertNull($config['transport']);
        $this->assertSame('env@saloenza.test', $config['from']['address']);
    }

    public function test_renewal_reminder_respects_disabled_notification_preference(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-01-01 10:00:00'));
        Mail::fake();

        $owner = $this->createOwnerUser(['email' => 'notify.owner@test.com']);
        $basic = SubscriptionPlan::query()->where('slug', 'basic')->firstOrFail();

        SalonSetting::query()->create([
            'saloon_id' => $owner->saloon_id,
            'group' => 'notifications',
            'key' => 'subscription_renewal_reminder',
            'value' => false,
        ]);
        \Illuminate\Support\Facades\Cache::forget('salon_settings.'.$owner->saloon_id);

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
        ]);

        Artisan::call('subscriptions:send-renewal-reminders');

        Mail::assertNotSent(SubscriptionRenewalReminderMail::class);
    }
}
