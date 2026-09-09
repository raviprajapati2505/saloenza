<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Support\Role\RoleCodes;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * @return list<array{
     *     code: string,
     *     name: string,
     *     scope: string,
     *     hierarchy_level: int,
     *     is_system: bool,
     *     permissions: list<string>
     * }>
     */
    public static function systemRoleDefinitions(): array
    {
        return [
            [
                'code' => RoleCodes::PLATFORM_SUPER_ADMIN,
                'name' => 'Super Admin',
                'scope' => 'platform',
                'hierarchy_level' => 0,
                'is_system' => true,
                'permissions' => ApplicationPermissionSeeder::allPermissionCodes(),
            ],
            [
                'code' => RoleCodes::AFFILIATE_PARTNER,
                'name' => 'Affiliate Partner',
                'scope' => 'affiliate',
                'hierarchy_level' => 5,
                'is_system' => true,
                'permissions' => ApplicationPermissionSeeder::affiliatePermissionCodes(),
            ],
            [
                'code' => RoleCodes::SALON_FRANCHISE_OWNER,
                'name' => 'Salon Franchise Owner',
                'scope' => 'salon',
                'hierarchy_level' => 10,
                'is_system' => true,
                'permissions' => ApplicationPermissionSeeder::tenantPermissionCodes(),
            ],
            [
                'code' => RoleCodes::SALON_FRANCHISE_MANAGER,
                'name' => 'Franchise Manager',
                'scope' => 'salon',
                'hierarchy_level' => 20,
                'is_system' => true,
                'permissions' => ApplicationPermissionSeeder::managerPermissionCodes(),
            ],
            [
                'code' => RoleCodes::SALON_BRANCH_MANAGER,
                'name' => 'Branch Manager',
                'scope' => 'branch',
                'hierarchy_level' => 30,
                'is_system' => true,
                'permissions' => ApplicationPermissionSeeder::branchManagerPermissionCodes(),
            ],
            [
                'code' => RoleCodes::SALON_STAFF,
                'name' => 'Staff',
                'scope' => 'branch',
                'hierarchy_level' => 40,
                'is_system' => true,
                'permissions' => ApplicationPermissionSeeder::staffPermissionCodes(),
            ],
        ];
    }

    public static function franchiseOwnerRole(): Role
    {
        return Role::query()
            ->where('code', RoleCodes::SALON_FRANCHISE_OWNER)
            ->firstOrFail();
    }

    public function run(): void
    {
        $this->call(LegacyRoleConsolidationSeeder::class);

        foreach (self::systemRoleDefinitions() as $definition) {
            $permissionIds = Permission::query()
                ->whereIn('code', $definition['permissions'])
                ->pluck('id')
                ->all();

            $role = Role::query()->updateOrCreate(
                ['code' => $definition['code']],
                [
                    'name' => $definition['name'],
                    'is_active' => true,
                    'is_system' => $definition['is_system'],
                    'scope' => $definition['scope'],
                    'hierarchy_level' => $definition['hierarchy_level'],
                    'saloon_id' => null,
                    'branch_id' => null,
                ],
            );

            $role->permissions()->sync($permissionIds);
        }

        Role::query()
            ->where('name', RoleCodes::LEGACY_SALOON_OWNER)
            ->whereNull('saloon_id')
            ->where('code', '!=', RoleCodes::SALON_FRANCHISE_OWNER)
            ->delete();
    }
}
