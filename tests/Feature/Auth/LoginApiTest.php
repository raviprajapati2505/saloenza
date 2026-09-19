<?php

namespace Tests\Feature\Auth;

use App\Models\Role;
use App\Models\Saloon;
use App\Models\User;
use App\Support\Role\RoleCodes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_login_skips_onboarding_even_when_timestamp_is_null(): void
    {
        $staff = $this->createStaffUser([
            'email' => 'staff.login@example.com',
            'password' => 'Pass@1234',
            'onboarding_completed_at' => null,
        ]);

        $this->postJson('/api/v1/public/login', [
            'login' => $staff->email,
            'password' => 'Pass@1234',
            'device_name' => 'phpunit',
        ])->assertOk()
            ->assertJsonPath('data.should_onboard', false);
    }

    public function test_branch_manager_login_skips_onboarding_even_when_timestamp_is_null(): void
    {
        $this->seedReferenceData();

        $managerRole = Role::findByCode(RoleCodes::SALON_BRANCH_MANAGER);
        $manager = $this->createStaffUser([
            'email' => 'manager.login@example.com',
            'password' => 'Pass@1234',
            'role_id' => $managerRole->id,
            'onboarding_completed_at' => null,
        ]);

        $this->postJson('/api/v1/public/login', [
            'login' => $manager->email,
            'password' => 'Pass@1234',
            'device_name' => 'phpunit',
        ])->assertOk()
            ->assertJsonPath('data.should_onboard', false);
    }

    public function test_franchise_owner_with_incomplete_onboarding_is_flagged(): void
    {
        $owner = $this->createOwnerUser([
            'email' => 'owner.onboard@example.com',
            'password' => 'Pass@1234',
            'onboarding_completed_at' => null,
        ]);

        $this->postJson('/api/v1/public/login', [
            'login' => $owner->email,
            'password' => 'Pass@1234',
            'device_name' => 'phpunit',
        ])->assertOk()
            ->assertJsonPath('data.should_onboard', true);
    }

    public function test_user_can_login_with_email(): void
    {
        $saloon = Saloon::query()->create([
            'name' => 'Glow House',
        ]);

        $user = User::factory()->create([
            'email' => 'owner@example.com',
            'phone' => '+919898989898',
            'saloon_id' => $saloon->id,
            'password' => 'Pass@1234',
        ]);

        $this->postJson('/api/v1/public/login', [
            'login' => $user->email,
            'password' => 'Pass@1234',
            'device_name' => 'phpunit',
        ])->assertOk()
            ->assertJsonPath('message', 'Login successful.');
    }

    public function test_user_can_login_with_phone(): void
    {
        $saloon = Saloon::query()->create([
            'name' => 'Glow House',
        ]);

        $user = User::factory()->create([
            'email' => 'phone-login@example.com',
            'phone' => '+919797979797',
            'saloon_id' => $saloon->id,
            'password' => 'Pass@1234',
        ]);

        $this->postJson('/api/v1/public/login', [
            'login' => $user->phone,
            'password' => 'Pass@1234',
            'device_name' => 'phpunit',
        ])->assertOk()
            ->assertJsonPath('message', 'Login successful.');
    }

    public function test_inactive_user_cannot_login(): void
    {
        $saloon = Saloon::query()->create([
            'name' => 'Glow House',
        ]);

        $user = User::factory()->create([
            'email' => 'inactive@example.com',
            'phone' => '+919191919191',
            'saloon_id' => $saloon->id,
            'password' => 'Pass@1234',
            'is_active' => false,
        ]);

        $this->postJson('/api/v1/public/login', [
            'login' => $user->email,
            'password' => 'Pass@1234',
            'device_name' => 'phpunit',
        ])->assertUnprocessable()
            ->assertJsonPath('message', 'Your account has been deactivated.');
    }
}
