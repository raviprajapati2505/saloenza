<?php

namespace Tests\Feature\Tenant;

use App\Models\SalonSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SalonSettingsApiTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = $this->createOwnerUser();
    }

    public function test_owner_can_view_resolved_salon_settings_with_platform_fallback(): void
    {
        Sanctum::actingAs($this->owner);

        $response = $this->getJson('/api/v1/salon/settings');

        $response->assertOk()
            ->assertJsonPath('data.branding.portal_name', config('tenant_settings.groups.branding.settings.portal_name.default'))
            ->assertJsonStructure([
                'data' => [
                    'groups',
                    'branding' => ['portal_name', 'logo_url', 'primary_color'],
                ],
            ]);
    }

    public function test_owner_can_save_tenant_branding_override(): void
    {
        Sanctum::actingAs($this->owner);

        $response = $this->putJson('/api/v1/salon/settings/branding', [
            'settings' => [
                'portal_name' => 'Luxe Hair Studio',
                'logo_path' => null,
                'primary_color' => '#112233',
                'secondary_color' => '#445566',
                'support_email' => 'hello@luxehair.test',
                'support_phone' => '+91 9111111111',
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.branding.portal_name', 'Luxe Hair Studio');

        $this->assertDatabaseHas('salon_settings', [
            'saloon_id' => $this->owner->saloon_id,
            'group' => 'branding',
            'key' => 'portal_name',
        ]);
    }

    public function test_tenant_override_takes_precedence_over_platform_default(): void
    {
        SalonSetting::query()->create([
            'saloon_id' => $this->owner->saloon_id,
            'group' => 'branding',
            'key' => 'portal_name',
            'value' => 'Custom Salon Portal',
        ]);

        Sanctum::actingAs($this->owner);

        $response = $this->getJson('/api/v1/salon/settings/branding');

        $response->assertOk()
            ->assertJsonPath('data.settings.portal_name.value', 'Custom Salon Portal')
            ->assertJsonPath('data.settings.portal_name.source', 'tenant');
    }

    public function test_owner_can_update_business_profile(): void
    {
        Sanctum::actingAs($this->owner);

        $response = $this->putJson('/api/v1/salon/business-profile', [
            'name' => 'Updated Salon Name',
            'phone' => '+91 9222222222',
            'whatsapp' => '+91 9333333333',
            'gst_number' => '27AAAAA0000A1Z5',
            'address' => '123 Main Street',
            'city' => 'Pune',
            'state' => 'Maharashtra',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Updated Salon Name')
            ->assertJsonPath('data.gst_number', '27AAAAA0000A1Z5');

        $this->assertDatabaseHas('saloons', [
            'id' => $this->owner->saloon_id,
            'name' => 'Updated Salon Name',
            'gst_number' => '27AAAAA0000A1Z5',
        ]);
    }

    public function test_owner_can_update_salon_settings_while_subscription_is_read_only(): void
    {
        $entitlements = app(\App\Support\Subscription\SubscriptionEntitlements::class);
        $basic = \App\Models\SubscriptionPlan::query()->where('slug', 'basic')->firstOrFail();
        $entitlements->assignPlan($this->owner->saloon, $basic, startTrial: false);

        $subscription = $entitlements->activeSubscription($this->owner->saloon->fresh());
        $this->assertNotNull($subscription);
        $subscription->update([
            'status' => 'expired',
            'ends_at' => now()->subDay(),
        ]);

        Sanctum::actingAs($this->owner->fresh());

        $this->putJson('/api/v1/salon/settings/branding', [
            'settings' => [
                'portal_name' => 'Read Only Override',
                'logo_path' => null,
                'primary_color' => '#112233',
                'secondary_color' => '#445566',
                'support_email' => 'hello@readonly.test',
                'support_phone' => '+91 9111111111',
            ],
        ])->assertOk()
            ->assertJsonPath('data.branding.portal_name', 'Read Only Override');
    }

    public function test_owner_me_includes_regional_settings_after_override(): void
    {
        SalonSetting::query()->create([
            'saloon_id' => $this->owner->saloon_id,
            'group' => 'regional',
            'key' => 'currency',
            'value' => 'USD',
        ]);
        SalonSetting::query()->create([
            'saloon_id' => $this->owner->saloon_id,
            'group' => 'regional',
            'key' => 'locale',
            'value' => 'en_US',
        ]);
        \Illuminate\Support\Facades\Cache::forget('salon_settings.'.$this->owner->saloon_id);

        Sanctum::actingAs($this->owner->fresh());

        $this->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.tenant.regional.currency', 'USD')
            ->assertJsonPath('data.tenant.regional.locale', 'en_US');
    }
}
