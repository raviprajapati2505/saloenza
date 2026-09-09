<?php

namespace Tests\Feature\Permission;

use App\Models\Permission;
use App\Models\Role;
use App\Support\Role\RoleCodes;
use Database\Seeders\PermissionBackfillSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PermissionBackfillSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_recreates_a_missing_permission_and_grants_it_to_matching_system_roles(): void
    {
        $this->forgetPermission('expenses.view');

        $this->seed(PermissionBackfillSeeder::class);

        $this->assertDatabaseHas('permissions', [
            'code' => 'expenses.view',
            'module' => 'expenses',
            'scope' => 'tenant',
        ]);

        $this->assertTrue($this->roleHas(RoleCodes::SALON_FRANCHISE_OWNER, 'expenses.view'));
        $this->assertTrue($this->roleHas(RoleCodes::SALON_BRANCH_MANAGER, 'expenses.view'));
    }

    public function test_it_does_not_grant_a_permission_to_roles_outside_its_definition(): void
    {
        $this->forgetPermission('expenses.view');

        $this->seed(PermissionBackfillSeeder::class);

        $this->assertFalse($this->roleHas(RoleCodes::SALON_STAFF, 'expenses.view'));
    }

    public function test_it_leaves_custom_role_permissions_untouched(): void
    {
        $customRole = $this->createTenantRole(['name' => 'Front Desk']);
        $customRole->permissions()->sync(
            Permission::query()->whereIn('code', ['appointments.view', 'customers.view'])->pluck('id')->all(),
        );

        $this->forgetPermission('expenses.view');

        $this->seed(PermissionBackfillSeeder::class);

        $this->assertEqualsCanonicalizing(
            ['appointments.view', 'customers.view'],
            $customRole->fresh()->permissions->pluck('code')->all(),
        );
    }

    public function test_it_does_not_regrant_a_permission_that_was_deliberately_revoked(): void
    {
        $owner = Role::query()->where('code', RoleCodes::SALON_FRANCHISE_OWNER)->firstOrFail();
        $analytics = Permission::query()->where('code', 'analytics.view')->firstOrFail();

        // The permission still exists, so the backfill must not treat it as a gap.
        $owner->permissions()->detach($analytics->id);

        $this->seed(PermissionBackfillSeeder::class);

        $this->assertFalse($this->roleHas(RoleCodes::SALON_FRANCHISE_OWNER, 'analytics.view'));
    }

    public function test_it_recreates_queue_view_all_and_grants_branch_manager_access(): void
    {
        $this->forgetPermission('queue.view_all');

        $this->seed(PermissionBackfillSeeder::class);

        $this->assertDatabaseHas('permissions', [
            'code' => 'queue.view_all',
            'module' => 'queue',
            'scope' => 'tenant',
        ]);

        $this->assertTrue($this->roleHas(RoleCodes::SALON_BRANCH_MANAGER, 'queue.view_all'));
        $this->assertFalse($this->roleHas(RoleCodes::SALON_STAFF, 'queue.view_all'));
    }

    public function test_it_syncs_updated_permission_module_metadata(): void
    {
        Permission::query()->where('code', 'queue.view')->update(['module' => 'appointments']);

        $this->seed(PermissionBackfillSeeder::class);

        $this->assertDatabaseHas('permissions', [
            'code' => 'queue.view',
            'module' => 'queue',
        ]);
    }

    public function test_running_twice_changes_nothing(): void
    {
        $this->forgetPermission('expenses.view');
        $this->seed(PermissionBackfillSeeder::class);

        $permissions = Permission::query()->count();
        $grants = DB::table('permission_role')->count();

        $this->seed(PermissionBackfillSeeder::class);

        $this->assertSame($permissions, Permission::query()->count());
        $this->assertSame($grants, DB::table('permission_role')->count());
    }

    private function forgetPermission(string $code): void
    {
        $permission = Permission::query()->where('code', $code)->firstOrFail();
        $permission->roles()->detach();
        $permission->delete();
    }

    private function roleHas(string $roleCode, string $permissionCode): bool
    {
        return Role::query()
            ->where('code', $roleCode)
            ->firstOrFail()
            ->permissions()
            ->where('code', $permissionCode)
            ->exists();
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedReferenceData();
    }
}
