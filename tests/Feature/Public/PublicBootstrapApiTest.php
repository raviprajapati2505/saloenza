<?php

namespace Tests\Feature\Public;

use App\Models\PlatformSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicBootstrapApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_platform_branding_returns_resolved_defaults(): void
    {
        PlatformSetting::query()->create([
            'group' => 'branding',
            'key' => 'portal_name',
            'value' => 'Glow Demo Platform',
        ]);

        \Illuminate\Support\Facades\Cache::forget('platform_settings.all');

        $this->getJson('/api/v1/public/platform-branding')
            ->assertOk()
            ->assertJsonPath('data.portal_name', 'Glow Demo Platform')
            ->assertJsonStructure([
                'data' => [
                    'portal_name',
                    'logo_url',
                    'primary_color',
                    'secondary_color',
                    'support_email',
                    'support_phone',
                ],
            ]);
    }

    public function test_public_subscription_modules_returns_catalog(): void
    {
        $this->getJson('/api/v1/public/subscription-modules')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'modules' => [['key', 'label']],
                    'nav_module_map',
                ],
            ]);
    }
}
