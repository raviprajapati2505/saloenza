<?php

namespace Tests;

use App\Models\AffiliatePartner;
use App\Models\Role;
use App\Models\Saloon;
use App\Models\SaloonBranch;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Support\Role\RoleCodes;
use App\Support\Subscription\SubscriptionEntitlements;
use Database\Seeders\ApplicationPermissionSeeder;
use Database\Seeders\PlatformSettingsSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Sanctum\Sanctum;

abstract class TestCase extends BaseTestCase
{
    protected function seedReferenceData(bool $withPlatformSettings = true): void
    {
        $this->seed(ApplicationPermissionSeeder::class);
        $this->seed(RoleSeeder::class);

        if ($withPlatformSettings) {
            $this->seed(PlatformSettingsSeeder::class);
        }
    }

    protected function seedPermissions(): void
    {
        $this->seed(ApplicationPermissionSeeder::class);
    }

    protected function createSaloon(array $attributes = []): Saloon
    {
        return Saloon::query()->create(array_merge([
            'name' => 'Test Saloon',
        ], $attributes));
    }

    protected function createOwnerRole(): Role
    {
        $this->seedReferenceData();

        return RoleSeeder::franchiseOwnerRole();
    }

    protected function createOwnerUser(array $attributes = []): User
    {
        $saloon = $this->createSaloon();
        $role = $this->createOwnerRole();
        $this->assignDefaultSubscription($saloon);

        return User::factory()->create(array_merge([
            'saloon_id' => $saloon->id,
            'role_id' => $role->id,
            'is_active' => true,
            'phone' => '+917000000001',
        ], $attributes));
    }

    protected function createStaffUser(array $attributes = []): User
    {
        $this->seedReferenceData();

        $saloon = $attributes['saloon'] ?? $this->createSaloon();
        unset($attributes['saloon']);
        $this->assignDefaultSubscription($saloon);

        $branch = SaloonBranch::query()->create([
            'saloon_id' => $saloon->id,
            'branch_name' => 'Test Branch',
            'business_address_1' => '123 Test Street',
            'city' => 'Mumbai',
            'state' => 'Maharashtra',
            'area_pincode' => '400001',
            'country' => 'India',
            'is_active' => true,
        ]);

        $staffRole = Role::findByCode(RoleCodes::SALON_STAFF);

        return User::factory()->create(array_merge([
            'saloon_id' => $saloon->id,
            'branch_id' => $branch->id,
            'role_id' => $staffRole->id,
            'is_active' => true,
            'phone' => '+917000000002',
        ], $attributes));
    }

    protected function createSystemAdmin(array $attributes = []): User
    {
        $email = strtolower(trim((string) ($attributes['email'] ?? ('sysadmin.'.uniqid('', true).'@example.test'))));
        unset($attributes['email']);

        $existing = User::query()->where('email', $email)->first();
        if ($existing !== null) {
            $existing->fill(array_merge([
                'is_active' => true,
            ], $attributes));
            $existing->forceFill(['is_system_admin' => true])->save();

            return $existing->fresh();
        }

        $phone = $attributes['phone'] ?? null;
        if ($phone === null || User::query()->where('phone', $phone)->exists()) {
            $attributes['phone'] = '+917'.str_pad((string) random_int(0, 999999999), 9, '0', STR_PAD_LEFT);
        }

        $user = User::factory()->create(array_merge([
            'email' => $email,
            'is_active' => true,
        ], $attributes));

        $user->forceFill(['is_system_admin' => true])->save();

        return $user->fresh();
    }

    protected function actingAsOwner(array $attributes = []): User
    {
        $user = $this->createOwnerUser($attributes);
        Sanctum::actingAs($user);

        return $user;
    }

    protected function makeRoleAttributes(array $overrides = []): array
    {
        $name = (string) ($overrides['name'] ?? 'Test Role');

        return array_merge([
            'name' => $name,
            'code' => $overrides['code'] ?? ('test.role.'.str_replace(' ', '.', strtolower($name)).'.'.uniqid()),
            'is_active' => true,
            'saloon_id' => null,
            'scope' => 'salon',
            'is_system' => false,
            'hierarchy_level' => 50,
        ], $overrides);
    }

    protected function createTenantRole(array $overrides = []): Role
    {
        return Role::query()->create($this->makeRoleAttributes($overrides));
    }

    protected function actingAsSystemAdmin(array $attributes = []): User
    {
        $user = $this->createSystemAdmin($attributes);
        Sanctum::actingAs($user);

        return $user;
    }

    protected function createAffiliatePartnerUser(array $attributes = []): User
    {
        $this->seedReferenceData();

        $role = Role::findByCode(RoleCodes::AFFILIATE_PARTNER);
        $user = User::factory()->create(array_merge([
            'role_id' => $role?->id,
            'is_active' => true,
            'phone' => '+917000000050',
            'onboarding_completed_at' => now(),
        ], $attributes));

        AffiliatePartner::query()->create([
            'user_id' => $user->id,
            'code' => 'AFF-TEST01-' . $user->id,
            'display_name' => $user->name,
            'status' => AffiliatePartner::STATUS_ACTIVE,
            'onboarding_commission_rate' => 12,
            'renewal_commission_rate' => 5,
            'commission_lock_days' => 30,
            'joined_at' => now(),
            'activated_at' => now(),
        ]);

        return $user->fresh(['affiliatePartner', 'role']);
    }

    protected function actingAsAffiliatePartner(array $attributes = []): User
    {
        $user = $this->createAffiliatePartnerUser($attributes);
        Sanctum::actingAs($user);

        return $user;
    }

    protected function seedSubscriptionPlans(): void
    {
        $this->seed(SubscriptionPlanSeeder::class);
    }

    protected function assignDefaultSubscription(Saloon $saloon, string $slug = 'free'): void
    {
        $this->seedSubscriptionPlans();

        $plan = SubscriptionPlan::query()->where('slug', $slug)->first();

        if ($plan === null && $slug === 'free') {
            $plan = SubscriptionPlan::query()->where('slug', 'free-trial')->first();
        }

        if ($plan !== null) {
            app(SubscriptionEntitlements::class)->assignPlan(
                $saloon,
                $plan,
                startTrial: $plan->trial_days > 0,
            );
        }
    }
}
