<?php

namespace Tests\Feature\Tenant;

use App\Mail\AppointmentConfirmationMail;
use App\Mail\SubscriptionRenewalReminderMail;
use App\Models\Customer;
use App\Models\PlatformSetting;
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

    public function test_mail_config_resolver_uses_platform_defaults_when_tenant_has_no_override(): void
    {
        $owner = $this->createOwnerUser();
        $resolver = app(TenantMailConfigResolver::class);

        $config = $resolver->resolve($owner->saloon);

        $this->assertSame(
            config('tenant_settings.groups.email.settings.from_email.default'),
            $config['from']['address'],
        );
    }

    public function test_mail_config_resolver_uses_tenant_smtp_when_configured(): void
    {
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

        app(\App\Services\Tenant\TenantSettingsService::class);
        \Illuminate\Support\Facades\Cache::forget('salon_settings.'.$owner->saloon_id);

        $config = app(TenantMailConfigResolver::class)->resolve($owner->saloon->fresh());

        $this->assertSame('tenant_dynamic_'.$owner->saloon_id, $config['mailer']);
        $this->assertSame('smtp.demo-salon.test', $config['transport']['host'] ?? null);
        $this->assertSame('hello@demo-salon.test', $config['from']['address']);
    }

    public function test_mail_config_resolver_ignores_tenant_smtp_when_use_custom_disabled(): void
    {
        $owner = $this->createOwnerUser();

        SalonSetting::query()->create([
            'saloon_id' => $owner->saloon_id,
            'group' => 'email',
            'key' => 'use_custom',
            'value' => false,
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

        $this->assertSame((string) config('mail.default', 'log'), $config['mailer']);
        $this->assertNull($config['transport']);
        $this->assertSame(
            (string) config('tenant_settings.groups.email.settings.from_email.default'),
            $config['from']['address'],
        );
    }

    public function test_mail_config_resolver_uses_workspace_smtp_when_host_is_configured(): void
    {
        $owner = $this->createOwnerUser();

        PlatformSetting::query()->create([
            'group' => 'email',
            'key' => 'use_custom',
            'value' => false,
        ]);
        PlatformSetting::query()->create([
            'group' => 'email',
            'key' => 'smtp_host',
            'value' => 'smtp.hostinger.com',
        ]);
        PlatformSetting::query()->create([
            'group' => 'email',
            'key' => 'smtp_port',
            'value' => 465,
        ]);
        PlatformSetting::query()->create([
            'group' => 'email',
            'key' => 'smtp_encryption',
            'value' => 'ssl',
        ]);
        PlatformSetting::query()->create([
            'group' => 'email',
            'key' => 'smtp_username',
            'value' => 'info@example.com',
        ]);
        PlatformSetting::query()->create([
            'group' => 'email',
            'key' => 'from_email',
            'value' => 'info@example.com',
        ]);

        \Illuminate\Support\Facades\Cache::forget('platform_settings.all');
        \Illuminate\Support\Facades\Cache::forget('salon_settings.'.$owner->saloon_id);

        $config = app(TenantMailConfigResolver::class)->resolve($owner->saloon->fresh());

        $this->assertSame('platform_smtp', $config['mailer']);
        $this->assertSame('smtp.hostinger.com', $config['transport']['host'] ?? null);
        $this->assertSame(465, $config['transport']['port'] ?? null);
        $this->assertSame('smtps', $config['transport']['scheme'] ?? null);
        $this->assertSame('info@example.com', $config['from']['address']);
    }

    public function test_mail_config_resolver_maps_port_465_tls_to_smtps(): void
    {
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
            'value' => 'smtp.hostinger.com',
        ]);
        SalonSetting::query()->create([
            'saloon_id' => $owner->saloon_id,
            'group' => 'email',
            'key' => 'smtp_port',
            'value' => 465,
        ]);
        SalonSetting::query()->create([
            'saloon_id' => $owner->saloon_id,
            'group' => 'email',
            'key' => 'smtp_encryption',
            'value' => 'tls',
        ]);

        \Illuminate\Support\Facades\Cache::forget('salon_settings.'.$owner->saloon_id);

        $config = app(TenantMailConfigResolver::class)->resolve($owner->saloon->fresh());

        $this->assertSame('smtps', $config['transport']['scheme'] ?? null);
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
            'renewal_reminder_days_sent' => null,
        ]);

        Artisan::call('subscriptions:send-renewal-reminders');

        Mail::assertNothingSent();

        Carbon::setTestNow();
    }

    public function test_appointment_notification_service_sends_confirmation_mail(): void
    {
        Mail::fake();

        $owner = $this->createOwnerUser(['email' => 'appointments.owner@test.com']);
        $staff = $this->createStaffUser(['saloon' => $owner->saloon, 'email' => 'staff.notify@test.com']);
        $customer = Customer::query()->create([
            'name' => 'Notify Customer',
            'email' => 'customer.notify@test.com',
            'phone' => '+917700000099',
            'is_active' => true,
        ]);
        $customer->attachSaloon($owner->saloon_id);

        $category = \App\Models\Category::query()->create(['name' => 'Hair', 'is_active' => true]);
        $service = \App\Models\Service::query()->create([
            'name' => 'Haircut',
            'category_id' => $category->id,
            'default_price' => 500,
            'duration_minutes' => 30,
            'is_active' => true,
        ]);

        $appointment = \App\Models\Appointment::query()->create([
            'saloon_id' => $owner->saloon_id,
            'branch_id' => $staff->branch_id,
            'customer_id' => $customer->id,
            'staff_id' => $staff->id,
            'service_id' => $service->id,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addMinutes(30),
            'status' => 'scheduled',
            'type' => 'appointment',
            'price' => 500,
            'discount' => 0,
            'grand_total' => 500,
            'created_by' => $owner->id,
        ]);

        app(\App\Services\Appointment\AppointmentNotificationService::class)
            ->appointmentCreated($appointment);

        Mail::assertSent(AppointmentConfirmationMail::class, function (AppointmentConfirmationMail $mail) use ($customer): bool {
            return $mail->hasTo($customer->email);
        });
    }
}
