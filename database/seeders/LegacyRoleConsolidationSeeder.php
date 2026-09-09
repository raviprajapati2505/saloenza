<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\Role\RoleCodes;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

/**
 * Merges legacy owner role rows into the canonical franchise owner role.
 */
class LegacyRoleConsolidationSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('roles')) {
            return;
        }

        $franchiseOwner = Role::query()
            ->where('code', RoleCodes::SALON_FRANCHISE_OWNER)
            ->first();

        if ($franchiseOwner === null) {
            $franchiseOwner = Role::query()->firstOrCreate(
                ['name' => RoleCodes::LEGACY_SALOON_OWNER, 'saloon_id' => null],
                ['is_active' => true],
            );
        }

        foreach (['Owner', RoleCodes::LEGACY_SALOON_OWNER] as $legacyName) {
            $legacyRole = Role::query()
                ->where('name', $legacyName)
                ->whereNull('saloon_id')
                ->where('id', '!=', $franchiseOwner->id)
                ->first();

            if ($legacyRole === null) {
                continue;
            }

            User::query()
                ->where('role_id', $legacyRole->id)
                ->update(['role_id' => $franchiseOwner->id]);

            $legacyRole->delete();
        }

        if (! Schema::hasTable('permissions') || ! Schema::hasTable('permission_role')) {
            return;
        }

        $permissionIds = Permission::query()
            ->whereIn('code', PermissionSeeder::ownerPermissionCodes())
            ->pluck('id')
            ->all();

        $franchiseOwner->permissions()->syncWithoutDetaching($permissionIds);
    }
}
