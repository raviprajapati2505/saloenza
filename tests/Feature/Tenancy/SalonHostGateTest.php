<?php

namespace Tests\Feature\Tenancy;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SalonHostGateTest extends TestCase
{
    use RefreshDatabase;

    public function test_unknown_salon_subdomain_is_rejected_before_login(): void
    {
        $this->withoutVite();

        $this->get('http://unknownsalon.saloenza.com/login')
            ->assertNotFound()
            ->assertSee('This salon is not onboarded')
            ->assertDontSee('Welcome back');

        $this->postJson('http://unknownsalon.saloenza.com/api/v1/public/login', [
            'login' => 'owner@example.com',
            'password' => 'password',
        ])->assertNotFound()
            ->assertJsonPath('message', 'This salon workspace was not found.');
    }

    public function test_platform_hosts_stay_available(): void
    {
        $this->withoutVite();

        $this->get('http://app.saloenza.com/login')->assertOk();
        $this->get('http://www.saloenza.com/login')->assertOk();
        $this->get('http://saloenza.com/login')->assertOk();
        $this->get('http://localhost/login')->assertOk();
    }

    public function test_onboarded_domain_allows_that_salon_and_blocks_other_accounts(): void
    {
        $owner = $this->createOwnerUser([
            'email' => 'owner@ravibeauty.test',
            'phone' => '+917000000011',
        ]);
        $owner->saloon->update(['domain' => 'ravibeautysalon']);

        $other = $this->createOwnerUser([
            'email' => 'other@example.test',
            'phone' => '+917000000012',
        ]);
        $other->saloon->update(['domain' => 'othersalon']);

        $this->withoutVite();

        $this->get('http://ravibeautysalon.saloenza.com/login')
            ->assertOk()
            ->assertSee('ravibeautysalon', false);

        $this->postJson('http://ravibeautysalon.saloenza.com/api/v1/public/login', [
            'login' => 'owner@ravibeauty.test',
            'password' => 'password',
        ])->assertOk();

        $this->postJson('http://ravibeautysalon.saloenza.com/api/v1/public/login', [
            'login' => 'other@example.test',
            'password' => 'password',
        ])->assertForbidden()
            ->assertJsonPath('message', 'This account does not belong to this salon.');
    }

    public function test_super_admin_can_sign_in_on_a_salon_domain(): void
    {
        $owner = $this->createOwnerUser([
            'email' => 'owner@ravibeauty.test',
            'phone' => '+917000000021',
        ]);
        $owner->saloon->update(['domain' => 'ravibeautysalon']);

        $admin = $this->createSystemAdmin([
            'email' => 'platform@saloenza.test',
        ]);

        $this->postJson('http://ravibeautysalon.saloenza.com/api/v1/public/login', [
            'login' => $admin->email,
            'password' => 'password',
        ])->assertOk();
    }

    public function test_workspace_domain_is_stored_in_lowercase(): void
    {
        Mail::fake();
        $this->seedReferenceData();
        $this->seed(\Database\Seeders\SubscriptionPlanSeeder::class);
        $this->actingAsSystemAdmin([
            'email' => 'admin.domain@saloenza.test',
        ]);

        $this->postJson('/api/v1/admin/onboarding', [
            'saloon' => [
                'business_name' => 'Ravi Beauty Salon',
                'domain' => 'RaviBeautySalon',
                'payment_type' => 'Monthly',
                'payment_amount' => 0,
                'transaction_id' => 'TXN-DOMAIN',
                'is_active' => true,
            ],
            'branch' => [
                'branch_name' => 'Main',
                'business_address_1' => '1 Street',
                'city' => 'Mumbai',
                'state' => 'Maharashtra',
                'area_pincode' => '400001',
                'country' => 'India',
                'is_active' => true,
            ],
            'user' => [
                'firstname' => 'Ravi',
                'lastname' => 'Owner',
                'email' => 'ravi.owner@example.test',
                'phone' => '+919800000001',
                'password' => 'Password@123',
                'password_confirmation' => 'Password@123',
                'is_active' => true,
            ],
        ])->assertCreated()
            ->assertJsonPath('data.saloon.domain', 'ravibeautysalon');

        $this->assertDatabaseHas('saloons', [
            'name' => 'Ravi Beauty Salon',
            'domain' => 'ravibeautysalon',
        ]);
    }
}
