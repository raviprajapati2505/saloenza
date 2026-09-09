<?php

namespace Tests\Feature\Role;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Saloon;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RoleApiTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsOwnerWithRolesModule(array $attributes = []): User
    {
        $user = $this->actingAsOwner($attributes);
        $this->assignDefaultSubscription($user->saloon, 'pro');

        return $user;
    }

    public function test_authenticated_user_can_manage_roles(): void
    {
        $this->actingAsOwnerWithRolesModule(['phone' => '+917111111111']);

        $createResponse = $this->postJson('/api/v1/roles', [
            'name' => 'Floor Lead',
            'is_active' => true,
        ]);

        $createResponse
            ->assertCreated()
            ->assertJsonPath('data.role.name', 'Floor Lead')
            ->assertJsonPath('data.role.is_active', true);

        $roleId = (int) $createResponse->json('data.role.id');

        $this->getJson('/api/v1/roles?page=1&per_page=100')
            ->assertOk()
            ->assertJsonFragment(['name' => 'Floor Lead']);

        $this->getJson("/api/v1/roles/{$roleId}")
            ->assertOk()
            ->assertJsonPath('data.role.name', 'Floor Lead');

        $this->putJson("/api/v1/roles/{$roleId}", [
            'name' => 'Floor Lead Updated',
            'is_active' => false,
        ])->assertOk()
            ->assertJsonPath('data.role.name', 'Floor Lead Updated')
            ->assertJsonPath('data.role.is_active', false);

        $this->deleteJson("/api/v1/roles/{$roleId}")
            ->assertOk()
            ->assertJsonPath('message', 'Role deleted successfully.');

        $this->assertDatabaseMissing('roles', [
            'id' => $roleId,
        ]);
    }

    public function test_user_can_clone_role(): void
    {
        $this->actingAsOwnerWithRolesModule(['phone' => '+917222222224']);

        $source = $this->createTenantRole(['name' => 'Front Desk']);

        $response = $this->postJson("/api/v1/roles/{$source->id}/clone", [
            'name' => 'Front Desk Copy',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.role.name', 'Front Desk Copy')
            ->assertJsonPath('message', 'Role cloned successfully.');

        $this->assertDatabaseHas('roles', [
            'name' => 'Front Desk Copy',
            'is_system' => false,
        ]);
    }

    public function test_user_can_assign_permissions_to_role(): void
    {
        $this->actingAsOwnerWithRolesModule(['phone' => '+917222222222']);

        $role = $this->createTenantRole([
            'name' => 'Receptionist',
            'hierarchy_level' => 50,
        ]);

        $permissionIds = Permission::query()
            ->whereIn('code', ['roles.view', 'products.view'])
            ->pluck('id')
            ->all();

        $this->putJson("/api/v1/roles/{$role->id}/permissions", [
            'permission_ids' => $permissionIds,
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Role permissions assigned successfully.')
            ->assertJsonCount(2, 'data.role.permissions');

        $this->assertDatabaseHas('permission_role', [
            'role_id' => $role->id,
            'permission_id' => $permissionIds[0],
        ]);
    }

    public function test_non_system_admin_sees_only_scoped_roles_without_query_param(): void
    {
        $user = $this->actingAsOwnerWithRolesModule(['phone' => '+917666666666']);
        $otherSaloon = Saloon::query()->create(['name' => 'Uptown']);

        $this->createTenantRole([
            'name' => 'Saloon A Manager',
            'saloon_id' => $user->saloon_id,
        ]);

        $this->createTenantRole([
            'name' => 'Saloon B Manager',
            'saloon_id' => $otherSaloon->id,
        ]);

        $this->getJson('/api/v1/roles?page=1&per_page=100')
            ->assertOk()
            ->assertJsonFragment(['name' => 'Saloon A Manager'])
            ->assertJsonMissing(['name' => 'Saloon B Manager']);
    }

    public function test_salon_owner_cannot_see_or_access_affiliate_partner_role(): void
    {
        $affiliateRole = Role::query()->where('code', \App\Support\Role\RoleCodes::AFFILIATE_PARTNER)->first();
        if ($affiliateRole === null) {
            $this->seed(\Database\Seeders\ApplicationPermissionSeeder::class);
            $this->seed(\Database\Seeders\RoleSeeder::class);
            $affiliateRole = Role::query()->where('code', \App\Support\Role\RoleCodes::AFFILIATE_PARTNER)->firstOrFail();
        }

        $this->actingAsOwnerWithRolesModule(['phone' => '+917666666667']);

        $this->getJson('/api/v1/roles?page=1&per_page=100')
            ->assertOk()
            ->assertJsonMissing(['name' => 'Affiliate Partner'])
            ->assertJsonMissing(['code' => $affiliateRole->code]);

        $this->getJson("/api/v1/roles/{$affiliateRole->id}")
            ->assertForbidden();
    }

    public function test_platform_admin_can_see_affiliate_partner_role(): void
    {
        $this->seed(\Database\Seeders\ApplicationPermissionSeeder::class);
        $this->seed(\Database\Seeders\RoleSeeder::class);

        $this->actingAsSystemAdmin(['phone' => '+917777777770']);

        $this->getJson('/api/v1/roles?page=1&per_page=100')
            ->assertOk()
            ->assertJsonFragment(['name' => 'Affiliate Partner']);
    }

    public function test_system_admin_sees_all_roles_even_when_saloon_id_query_param_is_passed(): void
    {
        $saloon = Saloon::query()->create(['name' => 'Downtown']);

        $this->createTenantRole([
            'name' => 'Saloon Manager',
            'saloon_id' => $saloon->id,
        ]);

        $this->actingAsSystemAdmin(['phone' => '+917777777777']);

        $this->getJson('/api/v1/roles?saloon_id='.$saloon->id.'&page=1&per_page=100')
            ->assertOk()
            ->assertJsonCount(1, 'data.roles');
    }

    public function test_authenticated_user_can_search_and_paginate_roles(): void
    {
        $this->actingAsOwnerWithRolesModule(['phone' => '+917222222223']);

        $this->createTenantRole(['name' => 'Color Specialist']);
        $this->createTenantRole(['name' => 'Receptionist', 'is_active' => false]);

        $this->getJson('/api/v1/roles?search=Color%20Specialist&page=1&per_page=100')
            ->assertOk()
            ->assertJsonCount(1, 'data.roles')
            ->assertJsonPath('data.roles.0.name', 'Color Specialist')
            ->assertJsonPath('data.meta.total', 1);

        $this->getJson('/api/v1/roles?is_active=0&page=1&per_page=100')
            ->assertOk()
            ->assertJsonCount(1, 'data.roles')
            ->assertJsonPath('data.roles.0.name', 'Receptionist');

        $this->getJson('/api/v1/roles?per_page=1&page=2')
            ->assertOk()
            ->assertJsonCount(1, 'data.roles')
            ->assertJsonPath('data.meta.current_page', 2)
            ->assertJsonPath('data.meta.per_page', 1)
            ->assertJsonPath('data.meta.total', 6)
            ->assertJsonPath('data.meta.last_page', 6);
    }
}
