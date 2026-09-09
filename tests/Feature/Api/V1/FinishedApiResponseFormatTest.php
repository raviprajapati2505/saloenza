<?php

namespace Tests\Feature\Api\V1;

use App\Models\Role;
use App\Models\Saloon;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FinishedApiResponseFormatTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedReferenceData();
    }

    public function test_register_response_format_remains_unchanged(): void
    {
        $response = $this->postJson('/api/v1/public/register', [
            'salon_name' => 'Downtown Studio',
            'name' => 'Owner Name',
            'email' => 'register.user@gmail.com',
            'phone' => '+91 9876543210',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
            'terms' => true,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('message', 'Registration completed successfully.');

        $this->assertSame(['message', 'data'], array_keys($response->json()));
        $this->assertSame(['user', 'saloon'], array_keys($response->json('data')));
        $this->assertSame(
            ['id', 'name', 'email', 'phone', 'saloon_id'],
            array_keys($response->json('data.user')),
        );
        $this->assertSame(['id', 'name'], array_keys($response->json('data.saloon')));
    }

    public function test_login_response_format_remains_unchanged(): void
    {
        $user = $this->createOwner(email: 'login.user@gmail.com');

        $response = $this->postJson('/api/v1/public/login', [
            'login' => $user->email,
            'password' => 'Password123',
            'device_name' => 'iphone',
        ]);

        $response->assertOk();
        $response->assertJsonPath('message', 'Login successful.');
        $response->assertJsonPath('data.should_onboard', true);
        $response->assertJsonPath('data.is_system_admin', false);

        $this->assertSame(['message', 'data'], array_keys($response->json()));
        $this->assertSame(
            ['user', 'tenant', 'affiliate_partner', 'is_system_admin', 'should_onboard', 'workspace', 'role', 'permissions', 'subscription_modules', 'subscription_limits', 'subscription_module_catalog', 'platform_branding', 'token'],
            array_keys($response->json('data')),
        );
        $this->assertSame(
            ['id', 'name', 'email', 'phone', 'photo', 'is_system_admin', 'role_id', 'saloon_id', 'branch_id'],
            array_keys($response->json('data.user')),
        );
        $this->assertSame(['id', 'name', 'is_active', 'activation_status', 'activation_pending', 'subscription_access_mode', 'subscription_expired', 'requested_plan', 'plan', 'subscription', 'subscription_summary', 'modules', 'limits', 'trial_ends_at', 'branding', 'regional'], array_keys($response->json('data.tenant')));
        $this->assertSame(['id', 'name', 'slug', 'price', 'billing_interval', 'is_free'], array_keys($response->json('data.tenant.plan')));
        $this->assertIsArray($response->json('data.permissions'));
        $this->assertNotEmpty($response->json('data.permissions'));
    }

    public function test_profile_response_formats_remain_unchanged(): void
    {
        Storage::fake('public');

        $user = $this->createOwner(email: 'profile.user@gmail.com');

        Sanctum::actingAs($user);

        $profileResponse = $this->putJson('/api/v1/profile', [
            'name' => 'Updated Owner',
            'phone' => '+91 9123456789',
        ]);

        $profileResponse->assertOk();
        $profileResponse->assertJsonPath('message', 'Profile updated successfully.');

        $this->assertSame(['message', 'data'], array_keys($profileResponse->json()));
        $this->assertSame(['user'], array_keys($profileResponse->json('data')));
        $this->assertSame(
            ['id', 'name', 'email', 'phone', 'photo', 'is_system_admin', 'role_id', 'saloon_id', 'branch_id'],
            array_keys($profileResponse->json('data.user')),
        );

        $passwordResponse = $this->putJson('/api/v1/password', [
            'current_password' => 'Password123',
            'new_password' => 'Updated123!',
            'new_password_confirmation' => 'Updated123!',
        ]);

        $passwordResponse->assertOk();
        $passwordResponse->assertExactJson([
            'message' => 'Password changed successfully.',
        ]);
    }

    public function test_me_response_format_remains_unchanged(): void
    {
        $user = $this->createOwner(email: 'me.user@gmail.com');

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/me');

        $response->assertOk();

        $this->assertSame(['data'], array_keys($response->json()));
        $this->assertSame(
            ['user', 'tenant', 'affiliate_partner', 'is_system_admin', 'should_onboard', 'workspace', 'role', 'permissions', 'subscription_modules', 'subscription_limits', 'subscription_module_catalog', 'platform_branding'],
            array_keys($response->json('data')),
        );
        $this->assertSame(
            ['id', 'name', 'email', 'phone', 'photo', 'is_system_admin', 'role_id', 'saloon_id', 'branch_id'],
            array_keys($response->json('data.user')),
        );
        $this->assertSame(['id', 'name', 'is_active', 'activation_status', 'activation_pending', 'subscription_access_mode', 'subscription_expired', 'requested_plan', 'plan', 'subscription', 'subscription_summary', 'modules', 'limits', 'trial_ends_at', 'branding', 'regional'], array_keys($response->json('data.tenant')));
        $this->assertSame(['id', 'name', 'slug', 'price', 'billing_interval', 'is_free'], array_keys($response->json('data.tenant.plan')));
        $this->assertIsArray($response->json('data.permissions'));
    }

    public function test_onboarding_response_messages_remain_unchanged(): void
    {
        $user = $this->createOwnerUser([
            'email' => 'onboarding.user@gmail.com',
            'phone' => '+91 9876543210',
            'password' => 'Password123',
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/onboarding/account', [
            'name' => 'Updated Onboarding Name',
        ])->assertExactJson([
            'message' => 'Account step saved.',
        ]);

        $this->postJson('/api/v1/onboarding/branch', [
            'branchName' => 'Main Branch',
            'address' => '42 Market Street',
            'city' => 'Mumbai',
            'state' => 'Maharashtra',
            'phone' => '+91 9988776655',
            'whatsapp' => '+91 9988776655',
            'gstNumber' => '27ABCDE1234F1Z5',
            'hours' => [
                'monday' => ['open' => '09:00', 'close' => '18:00'],
            ],
        ])->assertExactJson([
            'message' => 'Branch step saved.',
        ]);

        $this->postJson('/api/v1/onboarding/services', [
            'skipped' => false,
            'services' => [
                [
                    'name' => 'Haircut',
                    'category' => 'Hair',
                    'duration' => 45,
                    'price' => 499,
                ],
            ],
        ])->assertExactJson([
            'message' => 'Services step saved.',
        ]);

        $this->postJson('/api/v1/onboarding/complete')
            ->assertExactJson([
                'message' => 'Onboarding completed.',
            ]);
    }

    private function createOwner(string $email): User
    {
        return $this->createOwnerUser([
            'name' => 'Owner Name',
            'email' => $email,
            'phone' => '+91 9876543210',
            'password' => 'Password123',
        ]);
    }
}
