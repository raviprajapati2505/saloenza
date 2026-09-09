<?php

namespace Tests\Feature\Auth;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Saloon;
use App\Models\User;
use App\Support\Role\RoleCodes;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginPermissionsApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedReferenceData();
    }

    public function test_login_returns_role_permissions_and_system_admin_flag(): void
    {
        $saloon = Saloon::query()->create([
            'name' => 'Glow Studio',
        ]);

        $role = Role::findByCode(RoleCodes::SALON_FRANCHISE_OWNER);
        $role->permissions()->sync(
            Permission::query()->where('code', 'roles.view')->pluck('id'),
        );

        $user = User::factory()->create([
            'email' => 'owner-permissions@example.com',
            'phone' => '+917444444444',
            'password' => 'Pass@1234',
            'saloon_id' => $saloon->id,
            'role_id' => $role->id,
        ]);

        $this->postJson('/api/v1/public/login', [
            'login' => $user->email,
            'password' => 'Pass@1234',
        ])
            ->assertOk()
            ->assertJsonPath('data.is_system_admin', false)
            ->assertJsonPath('data.user.role_id', $role->id)
            ->assertJsonPath('data.user.is_system_admin', false)
            ->assertJsonPath('data.role.code', RoleCodes::SALON_FRANCHISE_OWNER)
            ->assertJsonPath('data.permissions', ['roles.view']);
    }

    public function test_system_admin_login_returns_all_permissions(): void
    {
        $user = $this->createSystemAdmin([
            'email' => 'system-admin@example.com',
            'phone' => '+917555555555',
            'password' => 'Pass@1234',
        ]);

        $response = $this->postJson('/api/v1/public/login', [
            'login' => $user->email,
            'password' => 'Pass@1234',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.is_system_admin', true)
            ->assertJsonPath('data.role', null)
            ->assertJsonCount(count(PermissionSeeder::definitions()), 'data.permissions');
    }

    public function test_platform_super_admin_role_login_returns_all_permissions(): void
    {
        $role = Role::findByCode(RoleCodes::PLATFORM_SUPER_ADMIN);

        $user = User::factory()->create([
            'email' => 'platform-owner@example.com',
            'phone' => '+917555555556',
            'password' => 'Pass@1234',
            'role_id' => $role->id,
            'saloon_id' => null,
            'is_active' => true,
        ]);

        $this->postJson('/api/v1/public/login', [
            'login' => $user->email,
            'password' => 'Pass@1234',
        ])
            ->assertOk()
            ->assertJsonPath('data.is_system_admin', false)
            ->assertJsonPath('data.role.code', RoleCodes::PLATFORM_SUPER_ADMIN)
            ->assertJsonCount(count(PermissionSeeder::definitions()), 'data.permissions');
    }
}
