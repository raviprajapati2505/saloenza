<?php

namespace Tests\Feature\Admin;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\Platform\PlatformPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PlatformPermissionMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedReferenceData();
    }

    private function createLimitedPlatformUser(string $email, string $phone, array $permissionCodes): User
    {
        $role = Role::query()->create([
            'name' => 'Platform Staff '.$email,
            'code' => 'platform.staff.'.uniqid(),
            'scope' => Role::SCOPE_PLATFORM,
            'is_system' => false,
            'is_active' => true,
            'hierarchy_level' => 10,
            'saloon_id' => null,
        ]);

        $role->permissions()->sync(
            Permission::query()->whereIn('code', $permissionCodes)->pluck('id')->all(),
        );

        $user = User::factory()->create([
            'email' => $email,
            'phone' => $phone,
            'is_active' => true,
            'role_id' => $role->id,
        ]);

        $user->forceFill(['is_system_admin' => false])->save();

        return $user->fresh(['role.permissions']);
    }

    public function test_platform_staff_with_onboarding_permission_can_access_admin_onboarding(): void
    {
        $user = $this->createLimitedPlatformUser(
            'platform-staff@example.com',
            '+917888888881',
            [PlatformPermissions::ONBOARDING_MANAGE],
        );

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/admin/onboarding')
            ->assertOk();
    }

    public function test_tenant_owner_cannot_access_admin_onboarding_without_platform_permission(): void
    {
        $this->actingAsOwner(['phone' => '+917888888882']);

        $this->getJson('/api/v1/admin/onboarding')
            ->assertForbidden()
            ->assertJsonPath('required_permissions.0', PlatformPermissions::ONBOARDING_MANAGE);
    }

    public function test_platform_staff_with_view_permission_cannot_delete_saloons(): void
    {
        $user = $this->createLimitedPlatformUser(
            'onboarding-only@example.com',
            '+917888888883',
            [PlatformPermissions::ONBOARDING_MANAGE],
        );

        Sanctum::actingAs($user);
        $saloon = $this->createSaloon();

        $this->deleteJson("/api/v1/saloons/{$saloon->id}")
            ->assertForbidden()
            ->assertJsonPath('required_permissions.0', PlatformPermissions::ONBOARDING_DELETE);
    }

    public function test_notification_routes_accept_any_of_or_permissions(): void
    {
        $user = $this->createLimitedPlatformUser(
            'upgrade-only@example.com',
            '+917888888884',
            [PlatformPermissions::UPGRADE_REQUESTS_VIEW],
        );

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/admin/notifications')
            ->assertOk()
            ->assertJsonPath('data.unread_count', 0);
    }
}
