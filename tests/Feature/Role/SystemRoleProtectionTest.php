<?php

namespace Tests\Feature\Role;

use App\Models\Role;
use App\Support\Role\RoleCodes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SystemRoleProtectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedReferenceData();
    }

    public function test_tenant_cannot_delete_system_role(): void
    {
        $this->actingAsOwner(['phone' => '+917111111112']);

        $systemRole = Role::findByCode(RoleCodes::SALON_FRANCHISE_OWNER);

        $this->deleteJson("/api/v1/roles/{$systemRole->id}")
            ->assertForbidden();
    }

    public function test_tenant_cannot_change_system_role_permissions(): void
    {
        $this->actingAsOwner(['phone' => '+917111111113']);

        $systemRole = Role::findByCode(RoleCodes::SALON_STAFF);
        $permId = \App\Models\Permission::query()->value('id');

        $this->putJson("/api/v1/roles/{$systemRole->id}/permissions", [
            'permission_ids' => [$permId],
        ])->assertForbidden();
    }

    public function test_role_seeder_creates_hierarchy_roles_by_code(): void
    {
        foreach (RoleCodes::systemCodes() as $code) {
            $this->assertDatabaseHas('roles', [
                'code' => $code,
                'is_system' => true,
            ]);
        }
    }
}
