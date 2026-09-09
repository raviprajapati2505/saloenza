<?php

namespace Tests\Feature\Tenant;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\Platform\PlatformPermissions;
use App\Support\Role\RoleCodes;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TenantPermissionMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedReferenceData();
    }

    public function test_staff_can_list_appointments_with_seeded_permissions(): void
    {
        $staff = $this->createStaffUser([
            'phone' => '+917888888891',
        ]);

        Sanctum::actingAs($staff);

        $this->getJson('/api/v1/appointments?page=1&per_page=10')
            ->assertOk();
    }

    public function test_staff_without_roles_view_cannot_list_roles(): void
    {
        $staff = $this->createStaffUser([
            'phone' => '+917888888892',
        ]);

        Sanctum::actingAs($staff);

        $this->getJson('/api/v1/roles')
            ->assertForbidden()
            ->assertJsonPath('required_permissions.0', 'roles.view');
    }

    public function test_staff_can_create_customers_with_seeded_permissions(): void
    {
        $staff = $this->createStaffUser([
            'phone' => '+917888888896',
        ]);

        Sanctum::actingAs($staff);

        $this->postJson('/api/v1/customers', [
            'name' => 'Walk-in Customer',
            'phone' => '+919999999991',
            'is_active' => true,
        ])->assertCreated();
    }

    public function test_system_admin_can_access_tenant_appointments(): void
    {
        $this->actingAsSystemAdmin([
            'phone' => '+917888888893',
        ]);

        $this->getJson('/api/v1/appointments?page=1&per_page=10')
            ->assertOk();
    }

    public function test_platform_staff_without_tenant_context_cannot_access_tenant_routes(): void
    {
        $role = Role::findByCode(RoleCodes::PLATFORM_SUPER_ADMIN);
        $role->permissions()->sync(
            Permission::query()->where('code', PlatformPermissions::ONBOARDING_MANAGE)->pluck('id')->all(),
        );

        $user = User::factory()->create([
            'email' => 'platform-only@example.com',
            'phone' => '+917888888894',
            'is_active' => true,
            'role_id' => $role->id,
            'saloon_id' => null,
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/appointments?page=1&per_page=10')
            ->assertForbidden()
            ->assertJsonPath('message', 'Your account is not assigned to a saloon.');
    }

    public function test_user_without_saloon_cannot_access_tenant_routes(): void
    {
        $ownerRole = RoleSeeder::franchiseOwnerRole();
        $user = User::factory()->create([
            'phone' => '+917888888895',
            'role_id' => $ownerRole->id,
            'saloon_id' => null,
            'is_active' => true,
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/appointments?page=1&per_page=10')
            ->assertForbidden()
            ->assertJsonPath('message', 'Forbidden. Tenant context required.');
    }
}
