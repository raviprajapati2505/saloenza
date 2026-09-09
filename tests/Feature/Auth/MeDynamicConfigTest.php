<?php

namespace Tests\Feature\Auth;

use App\Models\SalonSetting;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MeDynamicConfigTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SubscriptionPlanSeeder::class);
    }

    public function test_me_includes_regional_settings_and_module_catalog(): void
    {
        $owner = $this->createOwnerUser();

        SalonSetting::query()->create([
            'saloon_id' => $owner->saloon_id,
            'group' => 'regional',
            'key' => 'currency',
            'value' => 'USD',
        ]);
        \Illuminate\Support\Facades\Cache::forget('salon_settings.'.$owner->saloon_id);

        Sanctum::actingAs($owner->fresh());

        $this->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.tenant.regional.currency', 'USD')
            ->assertJsonStructure([
                'data' => [
                    'tenant' => [
                        'regional' => ['timezone', 'locale', 'currency', 'date_format', 'time_format'],
                        'branding' => ['portal_name', 'logo_url', 'primary_color'],
                    ],
                    'subscription_module_catalog' => [
                        'modules' => [['key', 'label']],
                        'nav_module_map',
                    ],
                    'platform_branding' => ['portal_name', 'logo_url', 'primary_color'],
                ],
            ]);
    }
}
