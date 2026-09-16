<?php

namespace Tests\Feature\Admin;

use App\Models\PlatformSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminPlatformSettingsApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedReferenceData();
    }

    public function test_super_admin_can_fetch_platform_settings(): void
    {
        $admin = $this->createSystemAdmin();
        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/admin/platform-settings')
            ->assertOk()
            ->assertJsonPath('data.groups.0.group', 'salon_referrals')
            ->assertJsonPath('data.groups.0.settings.commission_rate.value', 10)
            ->assertJsonPath('data.groups.0.settings.qualifying_months.value', 6);
    }

    public function test_super_admin_can_update_salon_referral_settings(): void
    {
        $admin = $this->createSystemAdmin();
        Sanctum::actingAs($admin);

        $this->putJson('/api/v1/admin/platform-settings/salon_referrals', [
            'settings' => [
                'commission_rate' => 12.5,
                'qualifying_months' => 8,
            ],
        ])
            ->assertOk()
            ->assertJsonPath('data.settings.commission_rate.value', 12.5)
            ->assertJsonPath('data.settings.qualifying_months.value', 8);

        $this->assertDatabaseHas('platform_settings', [
            'group' => 'salon_referrals',
            'key' => 'commission_rate',
        ]);

        $storedRate = PlatformSetting::query()
            ->where('group', 'salon_referrals')
            ->where('key', 'commission_rate')
            ->first()
            ?->value;

        $this->assertSame(12.5, (float) $storedRate);
        $this->assertSame($admin->id, PlatformSetting::query()->where('group', 'salon_referrals')->where('key', 'commission_rate')->value('updated_by'));
    }

    public function test_super_admin_can_save_email_settings_with_null_optional_fields(): void
    {
        $admin = $this->createSystemAdmin();
        Sanctum::actingAs($admin);

        $this->putJson('/api/v1/admin/platform-settings/email', [
            'settings' => [
                'use_custom' => false,
                'from_name' => 'Saloenza',
                'from_email' => 'hello@glowsuite.com',
                'reply_to' => null,
                'smtp_host' => '',
                'smtp_port' => 587,
                'smtp_encryption' => 'tls',
                'smtp_username' => '',
                'smtp_password' => '',
            ],
        ])
            ->assertOk()
            ->assertJsonPath('data.settings.reply_to.value', '');

        $storedReplyTo = PlatformSetting::query()
            ->where('group', 'email')
            ->where('key', 'reply_to')
            ->first()
            ?->value;

        $this->assertSame('', $storedReplyTo);
        $this->assertNull(
            PlatformSetting::query()
                ->where('group', 'email')
                ->where('key', 'smtp_password')
                ->first(),
        );
    }

    public function test_non_super_admin_cannot_access_platform_settings(): void
    {
        $owner = $this->createOwnerUser();
        Sanctum::actingAs($owner);

        $this->getJson('/api/v1/admin/platform-settings')
            ->assertForbidden();
    }

    public function test_referral_dashboard_uses_updated_platform_settings(): void
    {
        $admin = $this->createSystemAdmin();
        Sanctum::actingAs($admin);

        $this->putJson('/api/v1/admin/platform-settings/salon_referrals', [
            'settings' => [
                'commission_rate' => 15,
                'qualifying_months' => 4,
            ],
        ])->assertOk();

        $owner = $this->createOwnerUser([
            'email' => 'owner.settings.test@example.com',
        ]);
        $owner->saloon->update([
            'referral_code' => 'SETTEST1',
            'is_active' => true,
            'activation_status' => \App\Models\Saloon::ACTIVATION_ACTIVE,
        ]);

        Sanctum::actingAs($owner);

        $this->getJson('/api/v1/referrals/dashboard')
            ->assertOk()
            ->assertJsonPath('data.commission_rate', 15)
            ->assertJsonPath('data.qualifying_months', 4);
    }

    public function test_super_admin_can_upload_and_delete_platform_logo(): void
    {
        Storage::fake('public');

        $admin = $this->createSystemAdmin();
        Sanctum::actingAs($admin);

        $file = UploadedFile::fake()->image('platform-logo.png', 200, 80);

        $this->postJson('/api/v1/admin/platform-settings/branding/logo', [
            'logo' => $file,
        ])
            ->assertOk()
            ->assertJsonPath('data.branding.portal_name', fn ($value) => is_string($value) && $value !== '')
            ->assertJsonStructure([
                'data' => [
                    'branding' => ['portal_name', 'logo_url', 'primary_color'],
                ],
            ]);

        $storedPath = PlatformSetting::query()
            ->where('group', 'branding')
            ->where('key', 'logo_path')
            ->value('value');

        $this->assertIsString($storedPath);
        $this->assertNotSame('', $storedPath);
        Storage::disk('public')->assertExists($storedPath);

        $this->deleteJson('/api/v1/admin/platform-settings/branding/logo')
            ->assertOk()
            ->assertJsonPath('data.branding.logo_url', fn ($url) => is_string($url) && str_contains($url, 'glowsuite-logo'));

        Storage::disk('public')->assertMissing($storedPath);
        $this->assertNull(
            PlatformSetting::query()
                ->where('group', 'branding')
                ->where('key', 'logo_path')
                ->value('value'),
        );
    }
}
